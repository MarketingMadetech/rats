import sqlite3

def run_migrations():
    conn = sqlite3.connect('data/rats.db')
    cursor = conn.cursor()
    
    # Check current columns in rats table
    cursor.execute("PRAGMA table_info(rats)")
    columns = [col[1] for col in cursor.fetchall()]
    
    print("Colunas existentes:", columns)
    
    # Add pago_reembolso if it doesn't exist
    if 'pago_reembolso' not in columns:
        try:
            cursor.execute("ALTER TABLE rats ADD COLUMN pago_reembolso INTEGER DEFAULT 0")
            print("Coluna 'pago_reembolso' adicionada com sucesso.")
        except Exception as e:
            print("Erro ao adicionar 'pago_reembolso':", e)
            
    # Add lancado_reembolso if it doesn't exist
    if 'lancado_reembolso' not in columns:
        try:
            cursor.execute("ALTER TABLE rats ADD COLUMN lancado_reembolso INTEGER DEFAULT 0")
            print("Coluna 'lancado_reembolso' adicionada com sucesso.")
        except Exception as e:
            print("Erro ao adicionar 'lancado_reembolso':", e)

    # Add sem_lancamento if it doesn't exist
    if 'sem_lancamento' not in columns:
        try:
            cursor.execute("ALTER TABLE rats ADD COLUMN sem_lancamento INTEGER DEFAULT 0")
            print("Coluna 'sem_lancamento' adicionada com sucesso.")
        except Exception as e:
            print("Erro ao adicionar 'sem_lancamento':", e)
            
    conn.commit()
    conn.close()

if __name__ == '__main__':
    run_migrations()
