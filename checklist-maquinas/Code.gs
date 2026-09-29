/** Projeto Apps Script vinculado à planilha Madetech. */
const SPREADSHEET_ID = '1MTSngcSJbD4PBekO5SCeZHMMEJp80MxHnDwrA8Yph3Q';
const SHEET_NAME = 'Saída de Máquina';
const FOLDER_NAME = 'Checklist Saída de Máquina';

function doPost(e) {
  try {
    const request = JSON.parse(e.postData.contents);
    const secret = PropertiesService.getScriptProperties().getProperty('CHECKLIST_TOKEN');
    if (!secret || request.token !== secret) throw new Error('Acesso não autorizado.');
    delete request.token;
    return jsonResponse(submitChecklist(request));
  } catch (error) {
    console.error(error);
    return jsonResponse({ok:false,error:String(error.message || error)});
  }
}

function jsonResponse(value) {
  return ContentService.createTextOutput(JSON.stringify(value)).setMimeType(ContentService.MimeType.JSON);
}

function submitChecklist(request) {
  const lock = LockService.getScriptLock();
  const createdFiles = [];
  try {
    validateRequest(request);
    lock.waitLock(30000);
    const sheet = SpreadsheetApp.openById(SPREADSHEET_ID).getSheetByName(SHEET_NAME);
    if (!sheet) throw new Error('Aba Saída de Máquina não encontrada.');
    const last = sheet.getLastRow();
    if (last > 1) {
      const found = sheet.getRange(2, 1, last - 1, 1).createTextFinder(request.id).matchEntireCell(true).findNext();
      if (found) return {ok:true,id:request.id};
    }
    const folder = getFolder();
    const photoFile = saveImage(folder,request.foto.base64,request.foto.mime,request.id+'-pallet',['image/jpeg','image/png','image/webp']);
    createdFiles.push(photoFile);
    const signatureUrls = {};
    for (const type of ['separacao','carregamento','liberacao']) {
      const image = request.assinaturas[type];
      const file = saveImage(folder,image.substring('data:image/png;base64,'.length),'image/png',request.id+'-assinatura-'+type,['image/png']);
      createdFiles.push(file);
      signatureUrls[type] = file.getUrl();
    }
    const id = request.identificacao;
    const safe = value => {
      const text = String(value == null ? '' : value);
      return /^\s*[=+@]/.test(text) ? "'" + text : text;
    };
    const sentAt = Utilities.formatDate(new Date(),'America/Sao_Paulo','yyyy-MM-dd HH:mm:ss');
    const rows = request.itens.map(item => [
      request.id,sentAt,id.equipamento,id.modelo,id.numero_serie,
      id.nota_fiscal,id.pedido,id.data_saida,id.cliente,id.cidade_uf,
      id.transportadora,request.familia,id.separado_por,id.conferido_por,
      id.liberado_por,item.secao,item.descricao,item.quantidade,
      item.separacao,item.carregamento,request.pendencias,request.prazo_pendencias,
      request.responsavel_pendencias,request.observacoes,photoFile.getUrl(),
      signatureUrls.separacao,signatureUrls.carregamento,signatureUrls.liberacao,'01 — 09/2026'
    ].map(safe));
    if (last + rows.length > sheet.getMaxRows()) sheet.insertRowsAfter(sheet.getMaxRows(),last + rows.length - sheet.getMaxRows());
    sheet.getRange(last + 1,1,rows.length,29).setValues(rows);
    SpreadsheetApp.flush();
    return {ok:true,id:request.id};
  } catch (error) {
    createdFiles.forEach(file => {try {file.setTrashed(true);} catch (_) {}});
    console.error(error);
    return {ok:false,error:String(error.message || error)};
  } finally {
    try {lock.releaseLock();} catch (_) {}
  }
}

function validateRequest(request) {
  if (!request || !/^[a-f0-9]{24}$/.test(request.id || '')) throw new Error('Identificador inválido.');
  const id = request.identificacao || {};
  const fields = ['equipamento','modelo','numero_serie','nota_fiscal','pedido','data_saida','cliente','cidade_uf','transportadora','separado_por','conferido_por','liberado_por'];
  fields.forEach(key => {
    if (!String(id[key] || '').trim() || String(id[key]).length > 250) throw new Error('Preencha todos os campos de identificação.');
  });
  if (!/^\d{4}-\d{2}-\d{2}$/.test(id.data_saida)) throw new Error('Data de saída inválida.');
  if (String(id.separado_por).trim().toLocaleLowerCase('pt-BR') === String(id.conferido_por).trim().toLocaleLowerCase('pt-BR')) throw new Error('As duas conferências devem ser feitas por pessoas diferentes.');
  if (!/^C[1-5]$/.test(request.familia || '')) throw new Error('Família inválida.');
  const expected = [];
  Object.entries(CATALOG).forEach(([section,group]) => {
    if (section.startsWith('C') && section !== request.familia) return;
    group.itens.forEach((description,index) => expected.push({section,description,quantity:index < (group.quantidades || 0)}));
  });
  if (!Array.isArray(request.itens) || request.itens.length !== expected.length) throw new Error('Checklist incompleto.');
  let pending = false;
  request.itens.forEach((item,index) => {
    const expectedItem = expected[index];
    if (item.secao !== expectedItem.section || item.descricao !== expectedItem.description) throw new Error('Itens do checklist inválidos.');
    if (!['ok','na','pendente'].includes(item.separacao) || !['ok','na','pendente'].includes(item.carregamento)) throw new Error('Marque as duas conferências em todos os itens.');
    if (item.separacao === 'pendente' || item.carregamento === 'pendente') pending = true;
    const qty = String(item.quantidade || '');
    if (expectedItem.quantity && (item.separacao === 'ok' || item.carregamento === 'ok') && !/^[1-9]\d{0,4}$/.test(qty)) throw new Error('Informe as quantidades dos itens conferidos.');
    if (qty && !/^[1-9]\d{0,4}$/.test(qty)) throw new Error('Quantidade inválida.');
  });
  if (pending && (!String(request.pendencias || '').trim() || !String(request.prazo_pendencias || '').trim() || !String(request.responsavel_pendencias || '').trim())) throw new Error('Registre pendências, prazo e responsável.');
  if (String(request.pendencias || '').length > 5000 || String(request.observacoes || '').length > 5000) throw new Error('Texto de pendências ou observações muito longo.');
  if (!request.foto || !['image/jpeg','image/png','image/webp'].includes(request.foto.mime) || !request.foto.base64 || request.foto.base64.length > 2700000) throw new Error('Foto do pallet inválida ou maior que 2 MB.');
  for (const type of ['separacao','carregamento','liberacao']) {
    const image = String((request.assinaturas || {})[type] || '');
    if (!/^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(image) || image.length > 500000) throw new Error('Preencha as três assinaturas.');
  }
}

function getFolder() {
  const found = DriveApp.getFoldersByName(FOLDER_NAME);
  return found.hasNext() ? found.next() : DriveApp.createFolder(FOLDER_NAME);
}

function saveImage(folder,base64,mime,name,allowed) {
  if (!allowed.includes(mime) || !base64 || base64.length > 2700000) throw new Error('Imagem inválida.');
  const ext = mime === 'image/jpeg' ? 'jpg' : mime === 'image/webp' ? 'webp' : 'png';
  const blob = Utilities.newBlob(Utilities.base64Decode(base64),mime,name+'.'+ext);
  return folder.createFile(blob);
}
