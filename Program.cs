using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.IO.Compression;
using System.Net;
using System.Net.Sockets;
using System.Reflection;
using System.Threading;
using System.Windows.Forms;

[assembly: AssemblyTitle("VerifDoc - Plateforme de Vérification")]
[assembly: AssemblyDescription("Application officielle de contrôle et d'authentification des documents administratifs")]
[assembly: AssemblyConfiguration("")]
[assembly: AssemblyCompany("DGTTC")]
[assembly: AssemblyProduct("VerifDoc Desktop Autonome")]
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
        private const string MutexId = "Global\\VerifDoc_Platform_SingleInstance_App";
        
        private static string activeUrl = "http://localhost:8085";
        private static Process phpServerProcess = null;
        private static NotifyIcon trayIcon = null;
        private static Icon appIcon = null;

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
                    // Déjà ouvert : on réveille l'application
                    OpenBrowserWindow();
                    return;
                }

                LoadAppIcon();

                // 1. Détection ou démarrage du moteur backend
                bool serverReady = EnsureServerRunning();
                if (!serverReady)
                {
                    MessageBox.Show(
                        "Impossible d'initialiser le moteur d'exécution VerifDoc.\n" +
                        "Vérifiez que vous disposez des droits nécessaires pour exécuter des applications.",
                        AppTitle,
                        MessageBoxButtons.OK,
                        MessageBoxIcon.Error
                    );
                    return;
                }

                // 2. Initialisation de l'icône de zone de notification
                InitializeTrayIcon();

                // 3. Lancement de la fenêtre principale de bureau
                OpenBrowserWindow();

                // Gestion propre de la fermeture pour éteindre le serveur embarqué
                Application.ApplicationExit += (s, e) => StopEmbeddedPhpServer();

                // Boucle Windows Forms
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
                    return;
                }
            }
            catch { }
            appIcon = SystemIcons.Shield;
        }

        private static bool IsUrlResponding(string url, int timeoutMs = 1500)
        {
            try
            {
                HttpWebRequest request = (HttpWebRequest)WebRequest.Create(url + "/login.php");
                request.Method = "GET";
                request.Timeout = timeoutMs;
                request.ReadWriteTimeout = timeoutMs;
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

        private static int GetAvailablePort(int startingPort)
        {
            for (int port = startingPort; port < startingPort + 50; port++)
            {
                try
                {
                    TcpListener l = new TcpListener(IPAddress.Loopback, port);
                    l.Start();
                    l.Stop();
                    return port;
                }
                catch { }
            }
            return startingPort;
        }

        private static bool EnsureServerRunning()
        {
            // Cas 1 : Le serveur Docker ou local tourne déjà sur le port 8085
            if (IsUrlResponding("http://localhost:8085", 1000))
            {
                activeUrl = "http://localhost:8085";
                return true;
            }

            // Cas 2 : Aucun serveur n'est actif, lancement du moteur portable embarqué
            return StartEmbeddedPortableServer();
        }

        private static bool StartEmbeddedPortableServer()
        {
            string appData = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "VerifDoc"
            );
            string runtimeDir = Path.Combine(appData, "runtime");
            string appDir = Path.Combine(appData, "app");

            Directory.CreateDirectory(appData);
            Directory.CreateDirectory(runtimeDir);
            Directory.CreateDirectory(appDir);

            // A. Extraction du runtime PHP s'il n'est pas encore présent
            string phpExe = Path.Combine(runtimeDir, "php-win.exe");
            if (!File.Exists(phpExe))
            {
                phpExe = Path.Combine(runtimeDir, "php.exe");
            }

            if (!File.Exists(phpExe))
            {
                ExtractResource("php_runtime.zip", Path.Combine(appData, "php_runtime.zip"));
                try
                {
                    ZipFile.ExtractToDirectory(Path.Combine(appData, "php_runtime.zip"), runtimeDir);
                    File.Delete(Path.Combine(appData, "php_runtime.zip"));
                }
                catch { }

                phpExe = Path.Combine(runtimeDir, "php-win.exe");
                if (!File.Exists(phpExe))
                {
                    phpExe = Path.Combine(runtimeDir, "php.exe");
                }
            }

            // B. Détermination du dossier source des fichiers PHP/Web
            string currentBase = AppDomain.CurrentDomain.BaseDirectory;
            string webRoot = currentBase;

            // Si VerifDoc.exe a été déplacé seul sans les fichiers web autour, on extrait l'application embarquée
            if (!File.Exists(Path.Combine(currentBase, "login.php")) || !File.Exists(Path.Combine(currentBase, "verifier.php")))
            {
                webRoot = appDir;
                string testMarker = Path.Combine(appDir, "login.php");
                if (!File.Exists(testMarker))
                {
                    string zipApp = Path.Combine(appData, "web_app.zip");
                    ExtractResource("web_app.zip", zipApp);
                    try
                    {
                        ZipFile.ExtractToDirectory(zipApp, appDir);
                        File.Delete(zipApp);
                    }
                    catch { }
                }
            }

            // C. Attribution d'un port local libre
            int localPort = GetAvailablePort(8085);
            activeUrl = "http://127.0.0.1:" + localPort;

            // D. Démarrage du serveur PHP intégré
            try
            {
                ProcessStartInfo psi = new ProcessStartInfo();
                psi.FileName = phpExe;
                psi.Arguments = string.Format("-c \"{0}\" -S 127.0.0.1:{1} -t \"{2}\"",
                    Path.Combine(runtimeDir, "php.ini"),
                    localPort,
                    webRoot
                );
                psi.WorkingDirectory = webRoot;
                psi.WindowStyle = ProcessWindowStyle.Hidden;
                psi.CreateNoWindow = true;
                psi.UseShellExecute = false;

                phpServerProcess = Process.Start(psi);

                // Attente de démarrage (jusqu'à 5 secondes)
                for (int i = 0; i < 20; i++)
                {
                    if (IsUrlResponding(activeUrl, 300))
                    {
                        return true;
                    }
                    Thread.Sleep(250);
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Erreur au lancement du serveur embarqué : " + ex.Message);
            }

            return IsUrlResponding(activeUrl, 1000);
        }

        private static void ExtractResource(string resourceName, string targetPath)
        {
            Assembly asm = Assembly.GetExecutingAssembly();
            string[] names = asm.GetManifestResourceNames();
            string match = null;

            foreach (string n in names)
            {
                if (n.EndsWith(resourceName, StringComparison.OrdinalIgnoreCase))
                {
                    match = n;
                    break;
                }
            }

            if (match == null) return;

            using (Stream s = asm.GetManifestResourceStream(match))
            {
                if (s == null) return;
                using (FileStream fs = new FileStream(targetPath, FileMode.Create, FileAccess.Write))
                {
                    s.CopyTo(fs);
                }
            }
        }

        private static void StopEmbeddedPhpServer()
        {
            if (phpServerProcess != null && !phpServerProcess.HasExited)
            {
                try
                {
                    phpServerProcess.Kill();
                }
                catch { }
            }
        }

        private static void OpenBrowserWindow(string path = "")
        {
            string url = activeUrl + (string.IsNullOrEmpty(path) ? "" : "/" + path.TrimStart('/'));

            try
            {
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

                    Process.Start(psi);
                }
                else
                {
                    Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Erreur lors de l'ouverture de l'application : " + ex.Message, AppTitle);
            }
        }

        private static void InitializeTrayIcon()
        {
            ContextMenu contextMenu = new ContextMenu();

            MenuItem itemOpen = new MenuItem("🛡️ Ouvrir VerifDoc", (s, e) => OpenBrowserWindow());
            itemOpen.DefaultItem = true;
            contextMenu.MenuItems.Add(itemOpen);

            contextMenu.MenuItems.Add(new MenuItem("🔍 Vérifier un document", (s, e) => OpenBrowserWindow("verifier.php")));
            contextMenu.MenuItems.Add(new MenuItem("📋 Journal d'audit", (s, e) => OpenBrowserWindow("historique.php")));
            contextMenu.MenuItems.Add(new MenuItem("➕ Nouveau document", (s, e) => OpenBrowserWindow("ajouter_document.php")));
            contextMenu.MenuItems.Add(new MenuItem("👑 Espace Administrateur", (s, e) => OpenBrowserWindow("admin.php")));

            contextMenu.MenuItems.Add("-");

            contextMenu.MenuItems.Add(new MenuItem("❌ Quitter VerifDoc", (s, e) =>
            {
                if (trayIcon != null)
                {
                    trayIcon.Visible = false;
                }
                StopEmbeddedPhpServer();
                Application.Exit();
            }));

            trayIcon = new NotifyIcon();
            trayIcon.Text = AppTitle;
            trayIcon.Icon = appIcon;
            trayIcon.ContextMenu = contextMenu;
            trayIcon.Visible = true;

            trayIcon.DoubleClick += (s, e) => OpenBrowserWindow();
        }
    }
}
