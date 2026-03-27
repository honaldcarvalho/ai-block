#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdbool.h>
#include <unistd.h>
#include <termios.h>
#include <gtk/gtk.h>
#include "sqlite3.h"

#ifdef _WIN32
  #define HOSTS_FILE "C:\\Windows\\System32\\drivers\\etc\\hosts"
  #define DB_FILE "C:\\Program Files\\ai-block\\ai_block.db"
  #define UPDATE_CMD "curl -s -o \"C:\\Program Files\\ai-block\\ai_list_temp.json\""
  #define TEMP_JSON "C:\\Program Files\\ai-block\\ai_list_temp.json"
#else
  #define HOSTS_FILE "/etc/hosts"
  #define DB_FILE "/opt/ai-block/ai_block.db"
  #define UPDATE_CMD "curl -s -o /opt/ai-block/ai_list_temp.json"
  #define TEMP_JSON "/opt/ai-block/ai_list_temp.json"
#endif

#define DEFAULT_UPDATE_URL "https://croacworks.com.br/ai_list.json"

#define MARKER_START "# === INICIO AI-BLOCK ==="
#define MARKER_END "# === FIM AI-BLOCK ==="

sqlite3 *db = NULL;
bool db_is_open = false;

// Global GUI
GtkWidget *window, *status_label;
GtkWidget *entry_add;

// Hash function simples (DJB2 adaptada) para não usar libcrypto (zero dependencies)
// Retorna o hash como string hex. (Segurança básica para não salvar em plaintext)
void compute_hash(const char *str, char *out_hash) {
    unsigned long hash = 5381;
    int c;
    while ((c = *str++))
        hash = ((hash << 5) + hash) + c; /* hash * 33 + c */
    sprintf(out_hash, "%lx", hash);
}

// Inicializa o banco de dados e as tabelas
bool init_db() {
    if (sqlite3_open(DB_FILE, &db) != SQLITE_OK) {
        // Fallback to local directory if permission denied (e.g. running outside opt)
        if (sqlite3_open("ai_block.db", &db) != SQLITE_OK) {
            return false;
        }
    }
    db_is_open = true;

    char *err_msg = 0;
    const char *sql_config = "CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, value TEXT);";
    const char *sql_dominios = "CREATE TABLE IF NOT EXISTS dominios (url TEXT PRIMARY KEY);";

    sqlite3_exec(db, sql_config, 0, 0, &err_msg);
    sqlite3_exec(db, sql_dominios, 0, 0, &err_msg);
    return true;
}

void close_db() {
    if (db_is_open) {
        sqlite3_close(db);
        db_is_open = false;
    }
}

// Verifica se já existe uma senha mestra
bool has_master_password() {
    sqlite3_stmt *stmt;
    bool exists = false;
    if (sqlite3_prepare_v2(db, "SELECT value FROM config WHERE key='master_pass'", -1, &stmt, 0) == SQLITE_OK) {
        if (sqlite3_step(stmt) == SQLITE_ROW) {
            exists = true;
        }
        sqlite3_finalize(stmt);
    }
    return exists;
}

// Registra nova senha mestra
void set_master_password(const char *password) {
    char hash[64];
    compute_hash(password, hash);
    
    char sql[256];
    snprintf(sql, sizeof(sql), "INSERT OR REPLACE INTO config (key, value) VALUES ('master_pass', '%s');", hash);
    sqlite3_exec(db, sql, 0, 0, NULL);
}

// Autentica contra a senha do DB
bool authenticate(const char *password) {
    char hash[64];
    compute_hash(password, hash);
    
    sqlite3_stmt *stmt;
    bool auth_ok = false;
    if (sqlite3_prepare_v2(db, "SELECT value FROM config WHERE key='master_pass'", -1, &stmt, 0) == SQLITE_OK) {
        if (sqlite3_step(stmt) == SQLITE_ROW) {
            const char *db_hash = (const char *)sqlite3_column_text(stmt, 0);
            if (strcmp(db_hash, hash) == 0) {
                auth_ok = true;
            }
        }
        sqlite3_finalize(stmt);
    }
    return auth_ok;
}

// Auxiliar visual
void print_status(const char *msg) {
    if (status_label) {
        gtk_label_set_text(GTK_LABEL(status_label), msg);
    } else {
        printf("%s\n", msg);
    }
}

// Privilégios
bool check_privileges() {
    FILE *fp = fopen(HOSTS_FILE, "a");
    if (!fp) return false;
    fclose(fp);
    return true;
}

// Funções de Domínios no DB
void db_add_domain(const char *domain) {
    char *sql = sqlite3_mprintf("INSERT OR IGNORE INTO dominios (url) VALUES ('%q');", domain);
    sqlite3_exec(db, sql, 0, 0, NULL);
    sqlite3_free(sql);
}

void import_json(const char *filename) {
    FILE *fp = fopen(filename, "r");
    if (!fp) {
        printf("Erro ao abrir %s para importacao\n", filename);
        return;
    }
    char line[512];
    int count = 0;
    sqlite3_exec(db, "BEGIN TRANSACTION;", NULL, NULL, NULL);
    while (fgets(line, sizeof(line), fp)) {
        char *start = strchr(line, '"');
        if (start) {
            start++;
            char *end = strchr(start, '"');
            if (end && start != end) {
                if (strncmp(start, "domains", 7) == 0) continue;
                *end = '\0';
                db_add_domain(start);
                count++;
            }
        }
    }
    sqlite3_exec(db, "COMMIT;", NULL, NULL, NULL);
    fclose(fp);
    printf("Importados %d domínios do JSON com sucesso!\n", count);
}

void do_update(const char *url) {
    char cmd[1024];
    const char *target_url = url ? url : DEFAULT_UPDATE_URL;
    snprintf(cmd, sizeof(cmd), "%s %s", UPDATE_CMD, target_url);
    print_status("Baixando nova lista da Web...");
    int res = system(cmd);
    if (res == 0) {
        import_json(TEMP_JSON);
        remove(TEMP_JSON);
        print_status("Lista atualizada e salva no Banco de Dados com sucesso!");
    } else {
        print_status("Erro ao atualizar a lista via rede.");
    }
}

// Função de bloqueio (Escreve no hosts)
void do_unblock() {
    if (!check_privileges()) {
        print_status("Erro: Privilégios insuficientes (Rode como Admin/root)");
        return;
    }
    FILE *fp = fopen(HOSTS_FILE, "r");
    if (!fp) return;

    char temp_file[512];
    snprintf(temp_file, sizeof(temp_file), "%s.tmp", HOSTS_FILE);
    FILE *out = fopen(temp_file, "w");
    if (!out) {
        fclose(fp);
        print_status("Erro: não foi possível criar arquivo temporário");
        return;
    }

    char line[512];
    bool in_block = false;
    while (fgets(line, sizeof(line), fp)) {
        if (strstr(line, MARKER_START)) { in_block = true; continue; }
        if (in_block && strstr(line, MARKER_END)) { in_block = false; continue; }
        if (!in_block) fputs(line, out);
    }
    fclose(fp);
    fclose(out);
    
    remove(HOSTS_FILE);
    rename(temp_file, HOSTS_FILE);
    print_status("Acesso DESBLOQUEADO com sucesso.");
}

void do_block() {
    if (!check_privileges()) {
        print_status("Erro: Privilégios insuficientes (Rode como Admin/root)");
        return;
    }
    do_unblock(); // Limpa regras antes de re-escrever
    
    FILE *fp = fopen(HOSTS_FILE, "a");
    if (!fp) return;
    fprintf(fp, "\n%s\n", MARKER_START);
    
    // Le Dominios do banco de dados (SQLite)
    sqlite3_stmt *stmt;
    if (sqlite3_prepare_v2(db, "SELECT url FROM dominios", -1, &stmt, 0) == SQLITE_OK) {
        while (sqlite3_step(stmt) == SQLITE_ROW) {
            const char *url = (const char *)sqlite3_column_text(stmt, 0);
            fprintf(fp, "127.0.0.1\t%s\n", url);
        }
        sqlite3_finalize(stmt);
    }
    
    fprintf(fp, "%s\n", MARKER_END);
    fclose(fp);
    print_status("Bloqueio de IAs APLICADO com sucesso.");
}

// ============== GUI ============== 
bool gui_authenticated = false;

// Callbacks
void on_btn_block_clicked(GtkWidget *widget, gpointer data) { (void)widget; (void)data; do_block(); }
void on_btn_unblock_clicked(GtkWidget *widget, gpointer data) { (void)widget; (void)data; do_unblock(); }
void on_btn_add_clicked(GtkWidget *widget, gpointer data) {
    (void)widget; (void)data;
    const gchar *new_domain = gtk_entry_get_text(GTK_ENTRY(entry_add));
    if (strlen(new_domain) > 3) {
        db_add_domain(new_domain);
        char msg[256];
        snprintf(msg, sizeof(msg), "Domínio %s adicionado à lista local (DB)!", new_domain);
        print_status(msg);
        gtk_entry_set_text(GTK_ENTRY(entry_add), "");
    }
}
void on_btn_update_clicked(GtkWidget *widget, gpointer data) { (void)widget; (void)data; do_update(NULL); }

// Login dialog
void show_login_dialog() {
    GtkWidget *dialog = gtk_dialog_new_with_buttons("Autenticação Necessária", GTK_WINDOW(window), GTK_DIALOG_MODAL | GTK_DIALOG_DESTROY_WITH_PARENT, "Entrar", GTK_RESPONSE_ACCEPT, "Sair", GTK_RESPONSE_REJECT, NULL);
    
    GtkWidget *content_area = gtk_dialog_get_content_area(GTK_DIALOG(dialog));
    GtkWidget *label = gtk_label_new("Digite a Senha Mestra do AI-Block:");
    GtkWidget *entry_pass = gtk_entry_new();
    gtk_entry_set_visibility(GTK_ENTRY(entry_pass), FALSE); // Mascara a senha
    gtk_box_pack_start(GTK_BOX(content_area), label, FALSE, FALSE, 5);
    gtk_box_pack_start(GTK_BOX(content_area), entry_pass, FALSE, FALSE, 5);
    gtk_widget_show_all(dialog);

    while (true) {
        int result = gtk_dialog_run(GTK_DIALOG(dialog));
        if (result == GTK_RESPONSE_ACCEPT) {
            const gchar *pwd = gtk_entry_get_text(GTK_ENTRY(entry_pass));
            if (authenticate(pwd)) {
                gui_authenticated = true;
                break;
            } else {
                gtk_label_set_text(GTK_LABEL(label), "Senha Incorreta! Tente novamente:");
            }
        } else {
            exit(0); // Cancelled
        }
    }
    gtk_widget_destroy(dialog);
}

void show_setup_dialog() {
    GtkWidget *dialog = gtk_dialog_new_with_buttons("Nova Senha Mestra", GTK_WINDOW(window), GTK_DIALOG_MODAL | GTK_DIALOG_DESTROY_WITH_PARENT, "Criar", GTK_RESPONSE_ACCEPT, "Sair", GTK_RESPONSE_REJECT, NULL);
    
    GtkWidget *content_area = gtk_dialog_get_content_area(GTK_DIALOG(dialog));
    GtkWidget *label = gtk_label_new("Crie uma Senha Mestra para proteger o bloqueador:");
    GtkWidget *entry_pass = gtk_entry_new();
    gtk_entry_set_visibility(GTK_ENTRY(entry_pass), FALSE); 
    gtk_box_pack_start(GTK_BOX(content_area), label, FALSE, FALSE, 5);
    gtk_box_pack_start(GTK_BOX(content_area), entry_pass, FALSE, FALSE, 5);
    gtk_widget_show_all(dialog);

    if (gtk_dialog_run(GTK_DIALOG(dialog)) == GTK_RESPONSE_ACCEPT) {
        const gchar *pwd = gtk_entry_get_text(GTK_ENTRY(entry_pass));
        set_master_password(pwd);
        gui_authenticated = true;
    } else {
        exit(0);
    }
    gtk_widget_destroy(dialog);
}

void build_gui(int argc, char *argv[]) {
    gtk_init(&argc, &argv);
    window = gtk_window_new(GTK_WINDOW_TOPLEVEL);
    gtk_window_set_title(GTK_WINDOW(window), "AI-Block Visual Editor (Secure DB)");
    gtk_container_set_border_width(GTK_CONTAINER(window), 10);
    gtk_widget_set_size_request(window, 450, 250);

    g_signal_connect(window, "destroy", G_CALLBACK(gtk_main_quit), NULL);

    // Authentication Checks
    if (!has_master_password()) {
        show_setup_dialog();
    } else {
        show_login_dialog();
    }

    if (!gui_authenticated) return;

    GtkWidget *vbox = gtk_box_new(GTK_ORIENTATION_VERTICAL, 10);
    gtk_container_add(GTK_CONTAINER(window), vbox);

    GtkWidget *label = gtk_label_new("Painel de Controle de Inteligências Artificiais");
    gtk_box_pack_start(GTK_BOX(vbox), label, FALSE, FALSE, 0);

    status_label = gtk_label_new("Status: Autenticado com Sucesso.");
    gtk_box_pack_start(GTK_BOX(vbox), status_label, FALSE, FALSE, 0);

    // Control Buttons
    GtkWidget *hbox = gtk_button_box_new(GTK_ORIENTATION_HORIZONTAL);
    gtk_box_pack_start(GTK_BOX(vbox), hbox, FALSE, FALSE, 0);

    GtkWidget *btn_block = gtk_button_new_with_label("Bloquear IAs");
    GtkWidget *btn_unblock = gtk_button_new_with_label("Remover Bloqueio");
    GtkWidget *btn_update = gtk_button_new_with_label("Atualizar Lista (Web)");
    g_signal_connect(btn_block, "clicked", G_CALLBACK(on_btn_block_clicked), NULL);
    g_signal_connect(btn_unblock, "clicked", G_CALLBACK(on_btn_unblock_clicked), NULL);
    g_signal_connect(btn_update, "clicked", G_CALLBACK(on_btn_update_clicked), NULL);
    gtk_container_add(GTK_CONTAINER(hbox), btn_block);
    gtk_container_add(GTK_CONTAINER(hbox), btn_unblock);
    gtk_container_add(GTK_CONTAINER(hbox), btn_update);

    // Add Domain Box
    GtkWidget *hbox_add = gtk_box_new(GTK_ORIENTATION_HORIZONTAL, 5);
    gtk_box_pack_start(GTK_BOX(vbox), hbox_add, FALSE, FALSE, 10);
    entry_add = gtk_entry_new();
    gtk_entry_set_placeholder_text(GTK_ENTRY(entry_add), "ex: nova-ia.com");
    GtkWidget *btn_add = gtk_button_new_with_label("Adicionar Regra");
    g_signal_connect(btn_add, "clicked", G_CALLBACK(on_btn_add_clicked), NULL);
    gtk_box_pack_start(GTK_BOX(hbox_add), entry_add, TRUE, TRUE, 0);
    gtk_box_pack_start(GTK_BOX(hbox_add), btn_add, FALSE, FALSE, 0);

    if (!check_privileges()) {
         gtk_label_set_text(GTK_LABEL(status_label), "ALERTA: Execute como Admin/Root para poder salvar no /hosts");
    }

    gtk_widget_show_all(window);
    gtk_main();
}

// ============== CLI Helpers ============== 
char* get_cli_password() {
    static char password[64];
    struct termios oldt, newt;
    printf("Senha Mestra do AI-Block: ");
    tcgetattr(STDIN_FILENO, &oldt);
    newt = oldt;
    newt.c_lflag &= ~(ECHO);
    tcsetattr(STDIN_FILENO, TCSANOW, &newt);
    if (fgets(password, sizeof(password), stdin) != NULL) {
        password[strcspn(password, "\n")] = 0;
    }
    tcsetattr(STDIN_FILENO, TCSANOW, &oldt);
    printf("\n");
    return password;
}

int main(int argc, char *argv[]) {
    if (!init_db()) {
        printf("Erro crasso: impossivel criar banco de dados SQLite interno em %s\n", DB_FILE);
        return 1;
    }

    if (argc > 1) {
        if (!has_master_password()) {
            printf("Erro: Banco de dados SQLite sem Senha Mestra. Para criar a senha inicialize a interface grafica (rode sem argumentos).\n");
            close_db();
            return 1;
        }

        // Se for CLI, precisamos pedir senha na mao para os comandos ativos
        if (strcmp(argv[1], "--cli") != 0) {
            char *pass = get_cli_password();
            if (!authenticate(pass)) {
                printf("Acesso Negado. Senha incorreta.\n");
                close_db();
                return 1;
            }
        }

        if (strcmp(argv[1], "--block") == 0) {
            do_block();
        }
        else if (strcmp(argv[1], "--unblock") == 0) {
            do_unblock();
        }
        else if (strcmp(argv[1], "--add") == 0 && argc == 3) {
            db_add_domain(argv[2]);
            printf("Dominio %s salvo no DB.\n", argv[2]);
        }
        else if (strcmp(argv[1], "--import_json") == 0 && argc == 3) {
            import_json(argv[2]);
        }
        else if (strcmp(argv[1], "--update") == 0) {
            do_update(argc > 2 ? argv[2] : NULL);
        }
        else {
            printf("Uso CLI Autenticado:\n");
            printf("  --block : Bloquear IAs (Lido do DB SQLite nativo)\n");
            printf("  --unblock : Restaurar o hosts.\n");
            printf("  --add <url> : Insere domínio no banco SQLite.\n");
            printf("  --import_json <arquivo> : Importa JSON p/ o SQLite.\n");
            printf("  --update [url] : Busca JSON da web e popula o DB.\n");
        }
        close_db();
        return 0;
    }

    build_gui(argc, argv);
    close_db();
    return 0;
}
