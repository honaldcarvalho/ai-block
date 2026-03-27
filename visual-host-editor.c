#include <gtk/gtk.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

enum {
    COL_IP = 0,
    COL_HOST,
    NUM_COLS
};

GtkListStore *list_store;
GtkWidget *tree_view;
GtkWidget *entry_ip, *entry_host;

/* Load /etc/hosts into the list store */
void load_hosts() {
    FILE *fp = fopen("/etc/hosts", "r");
    if (!fp) return;

    char line[256];
    gtk_list_store_clear(list_store);

    while (fgets(line, sizeof(line), fp)) {
        if (line[0] == '#' || strlen(line) < 5) continue;
        char ip[64], host[128];
        if (sscanf(line, "%s %s", ip, host) == 2) {
            GtkTreeIter iter;
            gtk_list_store_append(list_store, &iter);
            gtk_list_store_set(list_store, &iter, COL_IP, ip, COL_HOST, host, -1);
        }
    }
    fclose(fp);
}

/* Save the list store back to /etc/hosts */
void save_hosts(GtkWidget *widget, gpointer data) {
    FILE *fp = fopen("/etc/hosts", "w");
    if (!fp) {
        g_printerr("Error: Run with sudo to save changes.\n");
        return;
    }

    GtkTreeIter iter;
    gboolean valid = gtk_tree_model_get_iter_first(GTK_TREE_MODEL(list_store), &iter);
    fprintf(fp, "## Managed by Croacworks Visual Host Editor\n");
    while (valid) {
        char *ip, *host;
        gtk_tree_model_get(GTK_TREE_MODEL(list_store), &iter, COL_IP, &ip, COL_HOST, &host, -1);
        fprintf(fp, "%s\t%s\n", ip, host);
        g_free(ip); g_free(host);
        valid = gtk_tree_model_iter_next(GTK_TREE_MODEL(list_store), &iter);
    }
    fclose(fp);
}

/* Remove selected entry */
void remove_entry(GtkWidget *widget, gpointer data) {
    GtkTreeSelection *selection = gtk_tree_view_get_selection(GTK_TREE_VIEW(tree_view));
    GtkTreeIter iter;
    if (gtk_tree_selection_get_selected(selection, NULL, &iter)) {
        gtk_list_store_remove(list_store, &iter);
    }
}

/* Edit selected entry: loads values into entries */
void edit_entry(GtkWidget *widget, gpointer data) {
    GtkTreeSelection *selection = gtk_tree_view_get_selection(GTK_TREE_VIEW(tree_view));
    GtkTreeIter iter;
    if (gtk_tree_selection_get_selected(selection, NULL, &iter)) {
        char *ip, *host;
        gtk_tree_model_get(GTK_TREE_MODEL(list_store), &iter, COL_IP, &ip, COL_HOST, &host, -1);
        gtk_entry_set_text(GTK_ENTRY(entry_ip), ip);
        gtk_entry_set_text(GTK_ENTRY(entry_host), host);
        gtk_list_store_remove(list_store, &iter); // Remove old to replace on Add
        g_free(ip); g_free(host);
    }
}

void add_entry(GtkWidget *widget, gpointer data) {
    const char *ip = gtk_entry_get_text(GTK_ENTRY(entry_ip));
    const char *host = gtk_entry_get_text(GTK_ENTRY(entry_host));
    if (strlen(ip) > 0 && strlen(host) > 0) {
        GtkTreeIter iter;
        gtk_list_store_append(list_store, &iter);
        gtk_list_store_set(list_store, &iter, COL_IP, ip, COL_HOST, host, -1);
        gtk_entry_set_text(GTK_ENTRY(entry_ip), "");
        gtk_entry_set_text(GTK_ENTRY(entry_host), "");
    }
}

int main(int argc, char *argv[]) {
    gtk_init(&argc, &argv);
    GtkWidget *window = gtk_window_new(GTK_WINDOW_TOPLEVEL);
    gtk_window_set_title(GTK_WINDOW(window), "Croacworks Visual Host Editor");
    gtk_container_set_border_width(GTK_CONTAINER(window), 10);
    gtk_widget_set_size_request(window, 500, 600);

    GtkWidget *vbox = gtk_box_new(GTK_ORIENTATION_VERTICAL, 5);
    gtk_container_add(GTK_CONTAINER(window), vbox);

    list_store = gtk_list_store_new(NUM_COLS, G_TYPE_STRING, G_TYPE_STRING);
    tree_view = gtk_tree_view_new_with_model(GTK_TREE_MODEL(list_store));
    GtkCellRenderer *renderer = gtk_cell_renderer_text_new();
    gtk_tree_view_insert_column_with_attributes(GTK_TREE_VIEW(tree_view), -1, "IP", renderer, "text", COL_IP, NULL);
    gtk_tree_view_insert_column_with_attributes(GTK_TREE_VIEW(tree_view), -1, "Host", renderer, "text", COL_HOST, NULL);
    gtk_box_pack_start(GTK_BOX(vbox), tree_view, TRUE, TRUE, 0);

    entry_ip = gtk_entry_new(); gtk_box_pack_start(GTK_BOX(vbox), entry_ip, FALSE, FALSE, 0);
    entry_host = gtk_entry_new(); gtk_box_pack_start(GTK_BOX(vbox), entry_host, FALSE, FALSE, 0);

    GtkWidget *hbox = gtk_button_box_new(GTK_ORIENTATION_HORIZONTAL);
    gtk_box_pack_start(GTK_BOX(vbox), hbox, FALSE, FALSE, 0);

    GtkWidget *btn_add = gtk_button_new_with_label("Add/Update");
    GtkWidget *btn_edit = gtk_button_new_with_label("Edit Selection");
    GtkWidget *btn_remove = gtk_button_new_with_label("Remove Selection");
    GtkWidget *btn_save = gtk_button_new_with_label("Save Changes");

    g_signal_connect(btn_add, "clicked", G_CALLBACK(add_entry), NULL);
    g_signal_connect(btn_edit, "clicked", G_CALLBACK(edit_entry), NULL);
    g_signal_connect(btn_remove, "clicked", G_CALLBACK(remove_entry), NULL);
    g_signal_connect(btn_save, "clicked", G_CALLBACK(save_hosts), NULL);
    g_signal_connect(window, "destroy", G_CALLBACK(gtk_main_quit), NULL);

    gtk_container_add(GTK_CONTAINER(hbox), btn_add);
    gtk_container_add(GTK_CONTAINER(hbox), btn_edit);
    gtk_container_add(GTK_CONTAINER(hbox), btn_remove);
    gtk_container_add(GTK_CONTAINER(hbox), btn_save);

    load_hosts();
    gtk_widget_show_all(window);
    gtk_main();
    return 0;
}
