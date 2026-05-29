# Sistema RAT - Relatório de Assistência Técnica
## Contexto de Desenvolvimento - Madetech

**Data de Criação:** 21/01/2026  
**Última Atualização:** 05/02/2026  
**Status:** ✅ Versão 2.0 - Restruturação Con Login + Sequencial por Técnico

---

## 📁 Estrutura de Arquivos (v2.0)

```
rats-mdt2026/
├── admin/                       # PAINEL ADMINISTRAÇÃO
│   ├── index.php                # Dashboard admin (filtros, stats)
│   ├── login.php                # Login para admin
│   ├── logout.php               # Logout
│   ├── tecnicos.php             # CRUD de técnicos
│   ├── rat-view.php             # Visualizar RAT (read-only)
│   ├── .htaccess                # Proteção de acesso
│   └── [estilos inline]
│
├── tecnico/                     # PAINEL TÉCNICO
│   ├── index.php                # Dashboard do técnico (seus RATs)
│   ├── login.php                # Login para técnico
│   ├── logout.php               # Logout
│   ├── criar-rat.php            # Criar novo RAT
│   ├── editar-rat.php           # Preencher/editar RAT completo
│   ├── .htaccess                # Proteção de acesso
│   └── [estilos inline]
│
├── api/
│   ├── config.php               # Configurações, DB, funções helper
│   ├── auth-admin.php           # Autenticação do admin
│   ├── auth-tecnico.php         # Autenticação do técnico
│   ├── enviar-email.php         # Geração de PDFs e disparo de emails
│   └── gerar-pdf.php            # (será criado) Renderizar PDFs
│
├── assets/
│   └── [CSS global se necessário]
│
├── data/
│   ├── .htaccess                # Bloqueia acesso ao banco
│   └── rats.db                  # Banco SQLite (criado automaticamente)
│
├── CONTEXTO_RAT.md
├── README.md
└── .htaccess                    # Bloqueios globais
```

---

## 🔗 URLs do Sistema (v2.0)

| Acesso | URL |
|--------|-----|
| **ADMIN** | |
| Login Admin | `https://sitenovo.madetech.com.br/rats-mdt2026/admin/login.php` |
| Dashboard Admin | `https://sitenovo.madetech.com.br/rats-mdt2026/admin/` |
| Gerenciar Técnicos | `https://sitenovo.madetech.com.br/rats-mdt2026/admin/tecnicos.php` |
| Visualizar RAT | `https://sitenovo.madetech.com.br/rats-mdt2026/admin/rat-view.php?id=X` |
| | |
| **TÉCNICO** | |
| Login Técnico | `https://sitenovo.madetech.com.br/rats-mdt2026/tecnico/login.php` |
| Dashboard Técnico | `https://sitenovo.madetech.com.br/rats-mdt2026/tecnico/` |
| Criar RAT | `https://sitenovo.madetech.com.br/rats-mdt2026/tecnico/criar-rat.php` |
| Editar RAT | `https://sitenovo.madetech.com.br/rats-mdt2026/tecnico/editar-rat.php?id=X` |

---

## 👥 Técnicos Cadastrados

1. Leonardo Araújo
2. Allan Araújo
3. André Neri
4. Fábio Leite
5. Gabriel Guilherme

---

## � Fluxo do Sistema (v2.0)

### Fluxo Admin
```
1. ADMIN acessa /admin/login.php
   └── Login com email/senha (padrão: admin@madetech.com.br / admin123)

2. ADMIN no /admin/ (Dashboard)
   ├── Visualiza todos RATs de todos técnicos
   ├── Filtra por técnico, status, mês
   ├── Clica em "Gerenciar Técnicos"
   │   └── Cria, edita ou deleta contas de técnico
   └── Clica em "Ver" para visualizar detalhes do RAT

3. ADMIN em "Gerenciar Técnicos"
   ├── Cria técnico: Alan, Leonardo, André, etc
   ├── Define email e telefone
   └── Sistema gera senha aleatória que técnico pode trocar
```

### Fluxo Técnico
```
1. TÉCNICO acessa /tecnico/login.php
   └── Login com email/senha (fornecido pelo admin)

2. TÉCNICO no /tecnico/ (Dashboard Pessoal)
   ├── Vê APENAS seus próprios RATs
   ├── Filtra por status: rascunho, preenchido, enviado
   └── Clica em "Criar Novo RAT"

3. TÉCNICO em /tecnico/criar-rat.php
   ├── Preenche:
   │   ├── Empresa (obrigatório)
   │   ├── Responsável (obrigatório)
   │   ├── Email do cliente
   │   ├── Endereço, cidade, estado (obrigatório)
   └── Sistema gera número: RAT-{seu_id}-{sequencial}
       └── Exemplo: Alan (id=1) → RAT-01-0001, RAT-01-0002, etc
       └── Outro técnico (id=2) → RAT-02-0001, RAT-02-0002, etc

4. TÉCNICO em /tecnico/editar-rat.php?id=X
   ├── Preenche completo:
   │   ├── Equipamento, modelo, matrícula, garantia
   │   ├── Defeito constatado + trabalho executado
   │   ├── Turnos (5 dias): manhã/tarde com horários
   │   ├── Horas viajadas (ida/volta por dia)
   │   ├── KMs rodados (ida/volta/total)
   │   ├── Adicionais: pedágio, hospedagem, alimentação
   │   ├── Assinatura cliente (canvas) + aceite
   │   └── Assinatura técnico (canvas) + aceite
   │
   ├── Clica "Salvar como Rascunho" (pode editar depois)
   │   └── Status: rascunho
   │
   └── Clica "Enviar RAT" (FINAL)
       ├── Valida campos obrigatórios e assinaturas
       ├── Status muda para: enviado
       ├── Gera 4 PDFs automáticos:
       │   ├── PDF COMPLETO → email técnico (tudo incluso)
       │   ├── PDF SUPORTE → suporte@madetech.com.br (sem dados viagem)
       │   ├── PDF MARKETING → marketing@madetech.com.br (básico)
       │   └── PDF CLIENTE → email_cliente (sem "Adicionais" pedágio/hospedagem/alimentação)
       │
       └── Envia 4 emails simultâneos com PDFs anexados
           └── Nome arquivo: {tecnico}_{numero}_{data}_{empresa}.pdf
```

---

## 🗄️ Estrutura do Banco de Dados (SQLite)

**Tabela: rats**

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INTEGER | PK, auto-increment |
| numero | VARCHAR(20) | RAT-0001, RAT-0002... |
| token | VARCHAR(64) | Acesso único ao formulário |
| status | VARCHAR(20) | pendente / enviado / preenchido |
| cliente_empresa | VARCHAR(255) | Nome da empresa |
| cliente_responsavel | VARCHAR(255) | Solicitante |
| endereco | TEXT | Endereço completo |
| cidade | VARCHAR(100) | Cidade |
| estado | VARCHAR(50) | UF |
| tecnico_nome | VARCHAR(255) | Nome do técnico |
| equipamento | VARCHAR(255) | Tipo (não usado no criar) |
| modelo_maquina | VARCHAR(255) | Modelo |
| matricula | VARCHAR(100) | Matrícula/Série |
| garantia | VARCHAR(10) | Sim/Não |
| defeito_constatado | TEXT | Descrição do defeito |
| trabalho_executado | TEXT | O que foi feito |
| horas_servico | VARCHAR(50) | Total horas serviço |
| turnos_json | TEXT | JSON com os 5 turnos |
| total_turnos | VARCHAR(50) | Ex: 40h00 |
| horas_viajadas_json | TEXT | JSON com ida/volta por dia |
| total_horas_viajadas | VARCHAR(50) | Ex: 12h30 |
| kms_rodados_json | TEXT | JSON com ida/volta/total por dia |
| total_kms | VARCHAR(50) | Total KMs |
| pedagio | DECIMAL(10,2) | Valor pedágio |
| hospedagem | DECIMAL(10,2) | Valor hospedagem |
| alimentacao | DECIMAL(10,2) | Valor alimentação |
| total_adicionais | DECIMAL(10,2) | Soma dos adicionais |
| assinatura_cliente | TEXT | Base64 da imagem canvas |
| assinatura_tecnico | TEXT | Base64 da imagem canvas |
| aceite_cliente | BOOLEAN | Checkbox aceite |
| aceite_tecnico | BOOLEAN | Checkbox aceite |
| data_criacao | DATETIME | Quando foi criado |
| data_envio | DATETIME | Quando foi enviado pro técnico |
| data_preenchimento | DATETIME | Quando o técnico preencheu |
| observacoes | TEXT | Notas internas (só admin vê) |

---

## ⚙️ Configurações Importantes

**Arquivo:** `api/config.php`

```php
define('SITE_URL', 'https://sitenovo.madetech.com.br');
define('BASE_URL', '/rats-mdt2026/');
define('WHATSAPP_NOTIFY', '5511920664794');
define('RAT_PREFIX', 'RAT');
```

---

## 🎨 Design

- **Cores principais:** #034c8c (azul), #f58220 (laranja), #10b981 (verde sucesso)
- **Fonte:** Inter (Google Fonts)
- **Ícones:** Font Awesome 6.5
- **Responsivo:** Sim, funciona no celular do técnico

---

## 📱 WhatsApp

**Número para notificações:** (11) 92066-4794

**Mensagens automáticas:**
1. Quando ADMIN envia pro técnico: "Olá! Segue o link para preenchimento do RAT RAT-0001..."
2. Quando TÉCNICO avisa que enviou: "✅ RAT Enviado! Número: RAT-0001, Técnico: Nome..."

---

## 🔧 Melhorias Pendentes

_(lista para próxima sessão)_

1. [ ] _aguardando input do usuário_
2. [ ] 
3. [ ] 

---

## 📝 Notas de Desenvolvimento

- Sistema substitui JotForm
- Banco SQLite (sem necessidade de MySQL)
- PDF gerado via print do navegador (não precisa de biblioteca)
- Assinaturas são canvas HTML5, salvas como base64
- Token de 64 caracteres = praticamente impossível adivinhar

---

## 🚀 Deploy

1. Fazer upload da pasta `rats-mdt2026/` para o servidor Hostinger
2. A pasta `data/` será criada automaticamente com o banco
3. Verificar permissões de escrita na pasta `data/`
4. Acessar a URL e testar criação de RAT
