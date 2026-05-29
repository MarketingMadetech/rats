# Sistema RAT v2.0 - Guia de Setup e Uso

## 🚀 Setup Inicial

### 1. Banco de Dados
O banco SQLite é criado automaticamente na primeira execução.

**Admin padrão (criado automaticamente):**
- Email: `admin@madetech.com.br`
- Senha: `admin123`
- ⚠️ Mude a senha após primeira login!

### 2. Configurar SMTP (Emails)
Edite `api/config.php` e configure:

```php
define('SMTP_HOST', 'mail.madetech.com.br');
define('SMTP_PORT', 587);
define('SMTP_USER', 'seu-email@madetech.com.br');
define('SMTP_PASS', 'sua-senha');
define('EMAIL_SUPORTE_CENTRAL', 'suporte@madetech.com.br');
define('EMAIL_MARKETING_CENTRAL', 'marketing@madetech.com.br');
```

### 3. Primeiro Acesso

#### Admin
1. Acesse: `https://sitenovo.madetech.com.br/rats-mdt2026/admin/login.php`
2. Email: `admin@madetech.com.br` | Senha: `admin123`
3. Vá para "Gerenciar Técnicos"
4. Crie contas para seus técnicos

#### Técnico
1. Recebe email com login (fornecido pelo admin)
2. Acessa: `https://sitenovo.madetech.com.br/rats-mdt2026/tecnico/login.php`
3. Cria seus próprios RATs

---

## 📊 Fluxo Completo

### Para o Administrador

```
ADMIN LOGIN
↓
PAINEL ADMIN (Dashboard)
├── Ver todos RATs
├── Filtrar por técnico, status, mês
├── Clique "Gerenciar Técnicos"
│   ├── Criar novo técnico
│   ├── Editar técnico
│   └── Deletar técnico
└── Clique "Ver" em um RAT
    └── Visualizar completo (read-only)
```

### Para o Técnico

```
TÉCNICO LOGIN
↓
PAINEL TÉCNICO (Pessoal)
├── Ver APENAS seus RATs
├── Filtrar por status
└── Clique "Criar Novo RAT"
    │
    └─→ Preencher Dados Iniciais
        ├── Empresa
        ├── Responsável
        ├── Email cliente
        ├── Endereço, Cidade, Estado
        └── Sistema gera: RAT-{seu_id}-{seq}
            
            └─→ Editar RAT (Completo)
                ├── Equipamento
                ├── Defeito + Trabalho
                ├── Turnos (5 dias)
                ├── Horas viajadas
                ├── KMs rodados
                ├── Adicionais (pedágio, hospedagem, alimentação)
                ├── Assinatura cliente + aceite
                ├── Assinatura técnico + aceite
                │
                ├── [SALVAR] = Rascunho (pode editar depois)
                │
                └── [ENVIAR] = FINAL
                    ├── Valida campos obrigatórios
                    ├── Gera 4 PDFs automáticos
                    │   ├── PDF COMPLETO → Técnico
                    │   ├── PDF SUPORTE → suporte@madetech.com.br
                    │   ├── PDF MARKETING → marketing@madetech.com.br
                    │   └── PDF CLIENTE → email_cliente (SEM ADICIONAIS)
                    └── Envia 4 emails automáticos
```

---

## 🔐 Números de RAT

Cada técnico tem seu próprio sequencial:

```
Alan (id=1):
- RAT-01-0001 (primeiro)
- RAT-01-0002 (segundo)
- RAT-01-0003 (terceiro)

Leonardo (id=2):
- RAT-02-0001 (primeiro dele)
- RAT-02-0002 (segundo dele)

André (id=3):
- RAT-03-0001
```

👉 **Formato:** `RAT-{ID_TECNICO_2_DIGITOS}-{SEQUENCIAL_4_DIGITOS}`

---

## 📧 Nomes dos PDFs

Quando o técnico envia, o arquivo gerado é:

```
{PRIMEIRO_NOME}_{NUMERO_RAT}_{DATA}_{EMPRESA}.pdf
```

**Exemplo:**
- Alan enviando: `Alan_RAT-01-0001_05-02-2026_EmpresaABC.pdf`

**4 versões geradas:**
1. `COMPLETO_Alan_RAT-01-0001_05-02-2026_EmpresaABC.pdf` → Técnico
2. `SUPORTE_Alan_RAT-01-0001_05-02-2026_EmpresaABC.pdf` → Suporte
3. `MARKETING_Alan_RAT-01-0001_05-02-2026_EmpresaABC.pdf` → Marketing
4. `Alan_RAT-01-0001_05-02-2026_EmpresaABC.pdf` → Cliente

---

## 🔒 Segurança & Isolamento

- ✅ Admin vê TODOS RATs de todos técnicos
- ✅ Técnico vê APENAS seus RATs
- ✅ Técnico não pode editar RAT de outro
- ✅ Tentativa de acesso direto retorna erro 403
- ✅ Senhas com hash bcrypt (seguro)
- ✅ Sessions PHP (auto-logout após 24h ou browser fecha)

---

## 🛠️ Troubleshooting

### Admin não consegue fazer login
1. Verifique se `api/config.php` foi criado corretamente
2. Verifique permissões da pasta `data/`
3. Tente apagar `data/rats.db` (vai recriar)

### Técnico não consegue fazer login
1. Verificar se a conta foi criada pelo admin
2. Verificar email/senha com o admin

### Emails não sendo enviados
1. Configurar SMTP em `api/config.php`
2. Instalar PHPMailer: `composer require phpmailer/phpmailer`
3. Log de erros em `enviar-email.php`

### PDFs não gerando
1. Atualmente gera como HTML (pode salvar como PDF)
2. Para PDF real: instale DomPDF
   ```
   composer require dompdf/dompdf
   ```

---

## 📋 Campos Obrigatórios

### Criar RAT
- [x] Empresa
- [x] Responsável
- [x] Endereço
- [x] Cidade
- [x] Estado

### Preencher RAT
- [x] Equipamento
- [x] Modelo
- [x] Defeito constatado
- [x] Trabalho executado
- [x] Assinatura cliente + aceite
- [x] Assinatura técnico + aceite

---

## 🎯 Checklist de Deploy

- [ ] Banco de dados criado
- [ ] Admin padrão senha trocada
- [ ] SMTP configurado
- [ ] Técnicos criados
- [ ] Tencnicos conseguem fazer login
- [ ] Técnico consegue criar RAT
- [ ] Técnico consegue enviar RAT
- [ ] Emails sendo recebidos
- [ ] PDFs sendo gerados
- [ ] Admin consegue visualizar todos RATs

---

## 📞 Suporte

Para dúvidas ou bugs, contate o desenvolvedor com:
1. Email que está tentando logar
2. Mensagem de erro (se houver)
3. Qual ação estava realizando

