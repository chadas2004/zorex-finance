        </main>
        <footer class="bg-gray-900 text-gray-200 py-4 text-center">
            © 2026 MoraFinanzen. Tous droits réservés.
        </footer>
    </div>
</div>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function sidebar() {
    return {
        open: true,
        darkMode: false,
        toggleSidebar() {
            this.open = !this.open;
        },
        init() {
            this.darkMode = localStorage.getItem('darkMode') === 'true';
        },
        $watch: {
            darkMode(value) {
                if(value) document.documentElement.classList.add('dark');
                else document.documentElement.classList.remove('dark');
                localStorage.setItem('darkMode', value);
            }
        }
    }
}
</script>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
