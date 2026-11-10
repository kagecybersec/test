using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net;
using System.Reflection;
using System.Threading;
using System.Windows.Forms;

[assembly: AssemblyTitle("VerifDoc - Plateforme de Vérification")]
[assembly: AssemblyDescription("Application officielle de contrôle et d'authentification des documents administratifs")]
[assembly: AssemblyConfiguration("")]
[assembly: AssemblyCompany("DGTTC")]
[assembly: AssemblyProduct("VerifDoc Desktop")]
[assembly: AssemblyCopyright("Copyright © 2026 DGTTC. Tous droits réservés.")]
[assembly: AssemblyTrademark("VerifDoc")]
[assembly: AssemblyCulture("")]
[assembly: AssemblyVersion("1.0.0.0")]
[assembly: AssemblyFileVersion("1.0.0.0")]

namespace VerifDoc
{
    static class Program
    {
        private const string AppTitle = "VerifDoc - Plateforme de Vérification (DGTTC)";
        private const string BaseUrl = "http://localhost:8085";
        private const string MutexId = "Global\\VerifDoc_Platform_SingleInstance_App";
        private static NotifyIcon trayIcon;
        private static Process browserProcess;
        private static Icon appIcon;

        [STAThread]
        static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            bool createdNew;
            using (Mutex mutex = new Mutex(true, MutexId, out createdNew))
            {
                if (!createdNew)
                {
                    // L'application tourne déjà en arrière-plan : ouvrir la fenêtre
                    OpenAppWindow();
                    return;
                }

                // Chargement de l'icône de l'application
                LoadAppIcon();

                // 1. Vérification et démarrage automatique du serveur si nécessaire
                if (!IsServerOnline())
                {
                    bool serverStarted = ShowSplashAndStartServer();
                    if (!serverStarted)
                    {
                        DialogResult res = MessageBox.Show(
                            "Le serveur VerifDoc n'est pas encore joignable sur " + BaseUrl + ".\n\n" +
                            "Souhaitez-vous tenter d'ouvrir l'application quand même ?",
                            "VerifDoc - Information de connexion",
                            MessageBoxButtons.YesNo,
                            MessageBoxIcon.Question
                        );
                        if (res != DialogResult.Yes)
                        {
                            return;
                        }
                    }
                }

                // 2. Configuration du System Tray (icône barre des tâches)
                InitializeTrayIcon();

                // 3. Lancement de la fenêtre principale de l'application
                OpenAppWindow();

                // Boucle de message de l'application
                Application.Run();
            }
        }

        private static void LoadAppIcon()
        {
            try
            {
                string iconPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "app.ico");
                if (File.Exists(iconPath))
                {
                    appIcon = new Icon(iconPath);
                }
                else
                {
                    appIcon = SystemIcons.Shield;
                }
            }
            catch
            {
                appIcon = SystemIcons.Shield;
            }
        }

        private static bool IsServerOnline()
        {
            try
            {
                HttpWebRequest request = (HttpWebRequest)WebRequest.Create(BaseUrl + "/login.php");
                request.Method = "GET";
                request.Timeout = 2500;
                request.ReadWriteTimeout = 2500;
                using (HttpWebResponse response = (HttpWebResponse)request.GetResponse())
                {
                    return response.StatusCode == HttpStatusCode.OK;
                }
            }
            catch
            {
                return false;
            }
        }

        private static bool ShowSplashAndStartServer()
        {
            using (Form splash = new Form())
            {
                splash.Text = AppTitle;
                splash.Size = new Size(460, 240);
                splash.FormBorderStyle = FormBorderStyle.FixedDialog;
                splash.StartPosition = FormStartPosition.CenterScreen;
                splash.MaximizeBox = false;
                splash.MinimizeBox = false;
                splash.TopMost = true;
                splash.Icon = appIcon;
                splash.BackColor = Color.FromArgb(244, 246, 249);

                Label lblHeader = new Label();
                lblHeader.Text = "🛡️ Plateforme de Vérification (DGTTC)";
                lblHeader.Font = new Font("Segoe UI", 12, FontStyle.Bold);
                lblHeader.ForeColor = Color.FromArgb(255, 122, 0); // Orange VerifDoc
                lblHeader.Location = new Point(20, 25);
                lblHeader.AutoSize = true;
                splash.Controls.Add(lblHeader);

                Label lblStatus = new Label();
                lblStatus.Text = "Démarrage des services et initialisation du registre...";
                lblStatus.Font = new Font("Segoe UI", 9.5f, FontStyle.Regular);
                lblStatus.ForeColor = Color.FromArgb(30, 41, 59);
                lblStatus.Location = new Point(24, 70);
                lblStatus.Size = new Size(400, 30);
                splash.Controls.Add(lblStatus);

                ProgressBar progressBar = new ProgressBar();
                progressBar.Style = ProgressBarStyle.Marquee;
                progressBar.MarqueeAnimationSpeed = 30;
                progressBar.Location = new Point(25, 115);
                progressBar.Size = new Size(395, 24);
                splash.Controls.Add(progressBar);

                Label lblPort = new Label();
                lblPort.Text = "Connexion au port 8085 (Docker / Apache / MySQL)...";
                lblPort.Font = new Font("Segoe UI", 8.5f, FontStyle.Italic);
                lblPort.ForeColor = Color.FromArgb(100, 116, 139);
                lblPort.Location = new Point(25, 155);
                lblPort.AutoSize = true;
                splash.Controls.Add(lblPort);

                // Lancement du conteneur en tâche de fond
                bool isReady = false;
                Thread bgThread = new Thread(() =>
                {
                    try
                    {
                        ProcessStartInfo psi = new ProcessStartInfo();
                        psi.FileName = "docker-compose";
                        psi.Arguments = "up -d";
                        psi.WorkingDirectory = AppDomain.CurrentDomain.BaseDirectory;
                        psi.WindowStyle = ProcessWindowStyle.Hidden;
                        psi.CreateNoWindow = true;
                        psi.UseShellExecute = false;

                        Process p = Process.Start(psi);
                        if (p != null)
                        {
                            p.WaitForExit(15000);
                        }
                    }
                    catch { }

                    // Attente active jusqu'à ce que le serveur réponde
                    for (int i = 0; i < 30; i++)
                    {
                        if (IsServerOnline())
                        {
                            isReady = true;
                            break;
                        }
                        Thread.Sleep(1000);
                    }

                    if (splash.InvokeRequired)
                    {
                        splash.BeginInvoke(new Action(() => splash.Close()));
                    }
                    else
                    {
                        splash.Close();
                    }
                });

                bgThread.IsBackground = true;
                bgThread.Start();

                splash.ShowDialog();
                return isReady || IsServerOnline();
            }
        }

        private static void OpenAppWindow(string path = "")
        {
            string url = BaseUrl + (string.IsNullOrEmpty(path) ? "" : "/" + path.TrimStart('/'));

            try
            {
                // Trouver le chemin d'Edge ou Chrome pour lancer le mode Application autonome (sans barre d'adresse)
                string edgePath = @"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe";
                if (!File.Exists(edgePath))
                {
                    edgePath = @"C:\Program Files\Microsoft\Edge\Application\msedge.exe";
                }

                string chromePath = @"C:\Program Files\Google\Chrome\Application\chrome.exe";
                if (!File.Exists(chromePath))
                {
                    chromePath = @"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe";
                }

                string browserExe = File.Exists(edgePath) ? edgePath : (File.Exists(chromePath) ? chromePath : null);

                if (browserExe != null)
                {
                    string profileDir = Path.Combine(
                        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                        "VerifDoc",
                        "DesktopProfile"
                    );

                    string args = string.Format(
                        "--app=\"{0}\" --user-data-dir=\"{1}\" --window-size=1260,860 --window-position=center",
                        url,
                        profileDir
                    );

                    ProcessStartInfo psi = new ProcessStartInfo();
                    psi.FileName = browserExe;
                    psi.Arguments = args;
                    psi.UseShellExecute = false;

                    browserProcess = Process.Start(psi);
                }
                else
                {
                    // Navigateur système par défaut si ni Edge ni Chrome n'est trouvé
                    Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show(
                    "Erreur lors de l'ouverture de l'application :\n" + ex.Message,
                    "VerifDoc",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error
                );
            }
        }

        private static void InitializeTrayIcon()
        {
            ContextMenu contextMenu = new ContextMenu();

            MenuItem itemOpen = new MenuItem("🛡️ Ouvrir VerifDoc", (s, e) => OpenAppWindow());
            itemOpen.DefaultItem = true; // Double-clic par défaut
            contextMenu.MenuItems.Add(itemOpen);

            contextMenu.MenuItems.Add(new MenuItem("🔍 Vérifier un document", (s, e) => OpenAppWindow("verifier.php")));
            contextMenu.MenuItems.Add(new MenuItem("📋 Journal d'audit", (s, e) => OpenAppWindow("historique.php")));
            contextMenu.MenuItems.Add(new MenuItem("➕ Nouveau document", (s, e) => OpenAppWindow("ajouter_document.php")));
            contextMenu.MenuItems.Add(new MenuItem("👑 Espace Administrateur", (s, e) => OpenAppWindow("admin.php")));

            contextMenu.MenuItems.Add("-"); // Séparateur

            contextMenu.MenuItems.Add(new MenuItem("🔄 Redémarrer les services", (s, e) =>
            {
                try
                {
                    ProcessStartInfo psi = new ProcessStartInfo("docker-compose", "restart");
                    psi.WorkingDirectory = AppDomain.CurrentDomain.BaseDirectory;
                    psi.WindowStyle = ProcessWindowStyle.Hidden;
                    psi.CreateNoWindow = true;
                    Process.Start(psi);

                    trayIcon.ShowBalloonTip(3000, "VerifDoc", "Redémarrage des services Docker en cours...", ToolTipIcon.Info);
                }
                catch (Exception ex)
                {
                    MessageBox.Show("Erreur : " + ex.Message);
                }
            }));

            contextMenu.MenuItems.Add(new MenuItem("🛑 Arrêter les conteneurs", (s, e) =>
            {
                try
                {
                    ProcessStartInfo psi = new ProcessStartInfo("docker-compose", "down");
                    psi.WorkingDirectory = AppDomain.CurrentDomain.BaseDirectory;
                    psi.WindowStyle = ProcessWindowStyle.Hidden;
                    psi.CreateNoWindow = true;
                    Process.Start(psi);

                    trayIcon.ShowBalloonTip(3000, "VerifDoc", "Arrêt des conteneurs en cours...", ToolTipIcon.Info);
                }
                catch (Exception ex)
                {
                    MessageBox.Show("Erreur : " + ex.Message);
                }
            }));

            contextMenu.MenuItems.Add("-"); // Séparateur

            contextMenu.MenuItems.Add(new MenuItem("❌ Quitter VerifDoc", (s, e) =>
            {
                trayIcon.Visible = false;
                Application.Exit();
            }));

            trayIcon = new NotifyIcon();
            trayIcon.Text = "VerifDoc - Plateforme de Vérification (DGTTC)";
            trayIcon.Icon = appIcon;
            trayIcon.ContextMenu = contextMenu;
            trayIcon.Visible = true;

            trayIcon.DoubleClick += (s, e) => OpenAppWindow();

            trayIcon.ShowBalloonTip(2500, "VerifDoc", "L'application est active et connectée au serveur.", ToolTipIcon.Info);
        }
    }
}
