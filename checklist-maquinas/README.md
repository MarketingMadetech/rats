# Checklist de Saída de Máquina

O formulário abre diretamente na hospedagem em `/checklist-maquinas/`. A interface é HTML, CSS e JavaScript. Um pequeno `submit.php` envia os dados ao Google Apps Script e devolve a confirmação; ele não usa banco local. A [aba Saída de Máquina](https://docs.google.com/spreadsheets/d/1MTSngcSJbD4PBekO5SCeZHMMEJp80MxHnDwrA8Yph3Q/edit?gid=2078431901) é o banco de dados.

Cada envio gera uma linha por item na planilha, com o mesmo ID para todas as linhas desse checklist. A foto do pallet e as três assinaturas são guardadas em uma pasta `Checklist Saída de Máquina` no Drive da conta que implantar o Apps Script; seus links ficam na planilha.

## 1. Implantar o Google Apps Script

1. Na [planilha Madetech](https://docs.google.com/spreadsheets/d/1MTSngcSJbD4PBekO5SCeZHMMEJp80MxHnDwrA8Yph3Q/edit?gid=2078431901), abra **Extensões → Apps Script**.
2. Cole `Code.gs` no arquivo `Code.gs` do projeto.
3. Crie um segundo arquivo de script chamado `Catalog.gs` e cole `Catalog.gs` desta pasta.
4. Em **Configurações do projeto → Propriedades do script**, crie `CHECKLIST_TOKEN` com uma sequência aleatória de pelo menos 32 caracteres. Guarde-a; ela não entra no JavaScript do site.
5. Em **Implantar → Nova implantação**, escolha **App da Web**, **Executar como: eu** e **Quem tem acesso: qualquer pessoa**, se o checklist deve funcionar sem login Google. Autorize acesso à planilha e ao Drive. Copie a URL terminada em `/exec`.

## 2. Publicar na hospedagem

1. Envie `index.html`, `style.css`, `app.js`, `catalog.js`, `submit.php` e `.htaccess` para a pasta pública `checklist-maquinas`. Requer PHP 8.0+ com extensão cURL e HTTPS. Apenas o endpoint de envio usa PHP.
2. Copie `config.example.php` para `config.local.php` no servidor e substitua a URL `/exec` e o token pelo mesmo valor da propriedade `CHECKLIST_TOKEN`. O arquivo local está no `.gitignore` e bloqueado por `.htaccess`; proteja-o também nas configurações da hospedagem. Como alternativa, configure as variáveis de ambiente `CHECKLIST_MAQUINAS_WEBHOOK_URL` e `CHECKLIST_MAQUINAS_WEBHOOK_TOKEN`.
3. Abra `https://SEU-DOMINIO/checklist-maquinas/` e faça um envio de teste. Confirme as linhas na aba e os links de foto e assinaturas.

O arquivo `config.local.php` contém um segredo. Não o envie ao Git nem o coloque em uma pasta que permita baixar arquivos PHP como texto. Para atualizar o Apps Script depois da primeira implantação, salve o código e publique uma nova versão em **Gerenciar implantações**.

Se alterar itens do checklist, mantenha `catalog.js` (site) e `Catalog.gs` (Apps Script) iguais. O comando `node verify.mjs` confere as regras principais de validação.
