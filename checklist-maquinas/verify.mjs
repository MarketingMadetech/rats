import fs from 'node:fs';
import assert from 'node:assert/strict';

const source = fs.readFileSync(new URL('./Catalog.gs', import.meta.url),'utf8') + '\n' + fs.readFileSync(new URL('./Code.gs', import.meta.url),'utf8');
assert.equal(fs.readFileSync(new URL('./Catalog.gs', import.meta.url),'utf8'),fs.readFileSync(new URL('./catalog.js', import.meta.url),'utf8'));
const {catalog, validate} = new Function(source + '\nreturn {catalog:CATALOG,validate:validateRequest};')();

function request(family='C3') {
  const items = Object.entries(catalog).flatMap(([section,group]) => {
    if (section.startsWith('C') && section !== family) return [];
    return group.itens.map((description,index) => ({
      secao:section,descricao:description,quantidade:index < (group.quantidades || 0) ? '1' : '',
      separacao:'ok',carregamento:'ok'
    }));
  });
  return {
    id:'1234567890abcdef12345678',familia:family,itens:items,
    identificacao:Object.fromEntries(['equipamento','modelo','numero_serie','nota_fiscal','pedido','data_saida','cliente','cidade_uf','transportadora','separado_por','conferido_por','liberado_por'].map(x => [x,x])),
    pendencias:'',prazo_pendencias:'',responsavel_pendencias:'',observacoes:'',
    foto:{mime:'image/png',base64:'aaaa'},
    assinaturas:Object.fromEntries(['separacao','carregamento','liberacao'].map(x => [x,'data:image/png;base64,aaaa']))
  };
}

const good = request();
good.identificacao.data_saida = '2026-09-29';
validate(good);
const incomplete = structuredClone(good);
incomplete.itens.pop();
assert.throws(() => validate(incomplete),/incompleto/);
const samePerson = structuredClone(good);
samePerson.identificacao.conferido_por = samePerson.identificacao.separado_por;
assert.throws(() => validate(samePerson),/pessoas diferentes/);
const noQuantity = structuredClone(good);
noQuantity.itens.find(item => item.quantidade).quantidade = '';
assert.throws(() => validate(noQuantity),/quantidades/);
const pending = structuredClone(good);
pending.itens[0].separacao = 'pendente';
assert.throws(() => validate(pending),/pendências/);
pending.pendencias = 'Manual pendente'; pending.prazo_pendencias = '2026-10-01'; pending.responsavel_pendencias = 'Fulano';
validate(pending);
console.log('Validações do checklist: OK');
