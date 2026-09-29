(() => {
  const form = document.getElementById('checklistForm');
  const sections = document.getElementById('sections');
  const familySelect = document.getElementById('familia');
  const errorBox = document.getElementById('error');
  const resultBox = document.getElementById('result');
  const signatures = {};
  const labels = {ok:'Conferido',na:'Não se aplica',pendente:'Pendente'};
  const signatureLabels = {separacao:'1ª conferência — separação',carregamento:'2ª conferência — carregamento',liberacao:'Liberação'};
  const fields = ['equipamento','modelo','numero_serie','nota_fiscal','pedido','data_saida','cliente','cidade_uf','transportadora','separado_por','conferido_por','liberado_por'];
  const state = {id:null};

  function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = false;
    errorBox.scrollIntoView({behavior:'smooth',block:'center'});
  }
  function addSelect(row, label, type, key) {
    const cell = document.createElement('div');
    const mobile = document.createElement('label');
    mobile.className = 'mobile-label';
    mobile.textContent = label;
    const select = document.createElement('select');
    select.name = type + '[' + key + ']';
    select.setAttribute('aria-label',label + ': ' + row.querySelector('.item-name').textContent);
    select.appendChild(new Option('Selecione',''));
    Object.entries(labels).forEach(([value,text]) => select.appendChild(new Option(text,value)));
    cell.append(mobile,select);
    row.appendChild(cell);
    return select;
  }
  Object.entries(CATALOG).forEach(([key,group]) => {
    if (key.startsWith('C')) familySelect.appendChild(new Option(group.titulo,key));
    const section = document.createElement('section');
    section.className = 'card checklist-section';
    if (key.startsWith('C')) section.dataset.family = key;
    const heading = document.createElement('h2');
    heading.textContent = group.titulo;
    section.appendChild(heading);
    if (key === 'D') {
      const note = document.createElement('p');
      note.className = 'hint';
      note.textContent = 'Confirme com o cliente, antes do embarque, quais itens são por conta dele e devem estar no local na entrega técnica.';
      section.appendChild(note);
    }
    const list = document.createElement('div');
    list.className = 'items';
    const header = document.createElement('div');
    header.className = 'item item-head';
    header.innerHTML = '<span>Item</span><span>Qtd.</span><span>1ª conferência</span><span>2ª conferência</span>';
    list.appendChild(header);
    group.itens.forEach((description,index) => {
      const itemKey = key + '_' + index;
      const row = document.createElement('div');
      row.className = 'item';
      row.dataset.section = key;
      row.dataset.index = String(index);
      const name = document.createElement('div');
      name.className = 'item-name';
      name.textContent = description;
      row.appendChild(name);
      const quantityCell = document.createElement('div');
      if (index < (group.quantidades || 0)) {
        const quantity = document.createElement('input');
        quantity.type = 'number'; quantity.min = '1'; quantity.max = '99999'; quantity.inputMode = 'numeric';
        quantity.name = 'quantidade[' + itemKey + ']';
        quantity.setAttribute('aria-label','Quantidade: ' + description);
        quantityCell.appendChild(quantity);
      } else {
        quantityCell.textContent = '—'; quantityCell.className = 'dash';
      }
      row.appendChild(quantityCell);
      addSelect(row,'1ª conferência','separacao',itemKey);
      addSelect(row,'2ª conferência','carregamento',itemKey);
      list.appendChild(row);
    });
    section.appendChild(list);
    sections.appendChild(section);
  });

  function updateFamily() {
    sections.querySelectorAll('[data-family]').forEach(section => {
      const active = section.dataset.family === familySelect.value;
      section.hidden = !active;
      section.querySelectorAll('input,select').forEach(input => input.disabled = !active);
    });
    sections.querySelectorAll('select').forEach(select => select.required = !select.disabled);
  }
  familySelect.addEventListener('change',updateFamily);
  updateFamily();

  Object.entries(signatureLabels).forEach(([type,label]) => {
    const wrap = document.createElement('div');
    wrap.className = 'signature';
    const title = document.createElement('strong'); title.textContent = label;
    const canvas = document.createElement('canvas');
    canvas.width = 560; canvas.height = 170;
    canvas.setAttribute('aria-label','Assinatura ' + label);
    const clear = document.createElement('button');
    clear.type = 'button'; clear.className = 'clear'; clear.textContent = 'Limpar assinatura';
    const accept = document.createElement('label'); accept.className = 'accept';
    const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.required = true;
    accept.append(checkbox,document.createTextNode('Assino e confirmo esta etapa'));
    wrap.append(title,canvas,clear,accept);
    document.getElementById('signatures').appendChild(wrap);
    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.strokeStyle = '#173957';
    let drawing = false, signed = false;
    const point = event => {
      const rect = canvas.getBoundingClientRect();
      return {x:(event.clientX-rect.left)*canvas.width/rect.width,y:(event.clientY-rect.top)*canvas.height/rect.height};
    };
    canvas.addEventListener('pointerdown', event => {
      drawing = true; signed = true; canvas.setPointerCapture(event.pointerId);
      const p = point(event); ctx.beginPath(); ctx.moveTo(p.x,p.y); ctx.lineTo(p.x+.1,p.y+.1); ctx.stroke();
    });
    canvas.addEventListener('pointermove', event => {
      if (!drawing) return; const p = point(event); ctx.lineTo(p.x,p.y); ctx.stroke();
    });
    canvas.addEventListener('pointerup',() => drawing = false);
    canvas.addEventListener('pointercancel',() => drawing = false);
    clear.addEventListener('click',() => {ctx.clearRect(0,0,canvas.width,canvas.height); signed = false;});
    signatures[type] = {canvas,checkbox,isSigned:() => signed};
  });

  function fileAsBase64(file) {
    return new Promise((resolve,reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result).split(',')[1]);
      reader.onerror = () => reject(new Error('Não foi possível ler a foto.'));
      reader.readAsDataURL(file);
    });
  }
  function newId() {
    const bytes = new Uint8Array(12);
    crypto.getRandomValues(bytes);
    return [...bytes].map(byte => byte.toString(16).padStart(2,'0')).join('');
  }
  form.addEventListener('submit',async event => {
    event.preventDefault();
    errorBox.hidden = true;
    const identification = {};
    fields.forEach(field => identification[field] = form.elements[field].value.trim());
    if (identification.separado_por.toLocaleLowerCase('pt-BR') === identification.conferido_por.toLocaleLowerCase('pt-BR')) {
      showError('A separação e o carregamento devem ser conferidos por pessoas diferentes.'); return;
    }
    const items = [];
    let pending = false;
    for (const row of sections.querySelectorAll('.item[data-section]')) {
      const [first,second] = row.querySelectorAll('select');
      if (first.disabled) continue;
      const quantityInput = row.querySelector('input[type="number"]');
      const quantity = quantityInput ? quantityInput.value.trim() : '';
      if (quantityInput && (first.value === 'ok' || second.value === 'ok') && (!quantity || Number(quantity) < 1)) {
        showError('Informe a quantidade dos itens conferidos.'); quantityInput.focus(); return;
      }
      if (first.value === 'pendente' || second.value === 'pendente') pending = true;
      items.push({secao:row.dataset.section,descricao:CATALOG[row.dataset.section].itens[Number(row.dataset.index)],quantidade:quantity,separacao:first.value,carregamento:second.value});
    }
    const pendencias = form.elements.pendencias.value.trim();
    const prazo = form.elements.prazo_pendencias.value;
    const responsavel = form.elements.responsavel_pendencias.value.trim();
    if (pending && (!pendencias || !prazo || !responsavel)) {
      showError('Descreva as pendências, o prazo e o responsável.'); return;
    }
    for (const pad of Object.values(signatures)) {
      if (!pad.isSigned() || !pad.checkbox.checked) {showError('Assine e confirme as três etapas.'); pad.canvas.scrollIntoView({behavior:'smooth',block:'center'}); return;}
    }
    const photo = form.elements.foto_pallet.files[0];
    if (!photo || photo.size > 2000000 || !['image/jpeg','image/png','image/webp'].includes(photo.type)) {
      showError('Anexe uma foto JPG, PNG ou WebP do pallet, com até 2 MB.'); return;
    }
    if (!state.id) state.id = newId();
    const button = form.querySelector('.submit');
    button.disabled = true; button.textContent = 'Enviando à planilha...';
    try {
      const payload = {
        id:state.id, enviado_em:new Date().toISOString(), identificacao:identification,
        familia:familySelect.value, itens:items, pendencias,
        prazo_pendencias:prazo, responsavel_pendencias:responsavel,
        observacoes:form.elements.observacoes.value.trim(),
        assinaturas:Object.fromEntries(Object.entries(signatures).map(([type,pad]) => [type,pad.canvas.toDataURL('image/png')])),
        foto:{mime:photo.type,base64:await fileAsBase64(photo)}
      };
      const response = await fetch('submit.php', {
        method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',
        body:JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok || !result.ok || result.id !== state.id) throw new Error(result.error || 'Não foi possível confirmar a gravação.');
      document.getElementById('formArea').hidden = true;
      resultBox.innerHTML = '<h1>Checklist registrado</h1><p>Dados, foto e assinaturas foram enviados à aba “Saída de Máquina”.</p>';
      resultBox.hidden = false;
      resultBox.scrollIntoView({behavior:'smooth',block:'start'});
    } catch (error) {
      button.disabled = false; button.textContent = 'Registrar checklist na planilha';
      showError(error.message || 'Falha no envio.');
    }
  });
})();
