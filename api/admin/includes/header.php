
<!-- HEADER + SIDEBAR -->
<div x-data="sidebar()" class="flex h-screen bg-gray-100 dark:bg-gray-900">

    <!-- Sidebar -->
    <aside :class="open ? 'w-64' : 'w-16'" class="bg-white dark:bg-gray-800 h-full shadow transition-all duration-300 flex flex-col">
        <div class="flex items-center justify-between p-4 border-b dark:border-gray-700">
            <span class="text-xl font-bold text-blue-800 dark:text-white" x-show="open">MoraFinance</span>
            <button @click="toggleSidebar()" class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700">
                <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-700 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-700 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="flex-1 p-4 space-y-2">
            <a href="index.php" class="flex items-center gap-3 p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                <span class="material-icons">dashboard</span>
                <span x-show="open">Dashboard</span>
            </a>
            <a href="demandes.php" class="flex items-center gap-3 p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                <span class="material-icons">receipt_long</span>
                <span x-show="open">Demandes</span>
            </a>

            <a href="logout.php" class="flex items-center gap-3 p-2 rounded text-red-500 hover:underline transition">
                <span class="material-icons">logout</span>
                <span x-show="open">Déconnexion</span>
            </a>
        </nav>
    </aside>

    <!-- Contenu principal -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Navbar -->
        <header class="bg-white dark:bg-gray-900 shadow p-4 flex justify-between items-center">
           
           
        </header>

        <main class="flex-1 overflow-auto p-6">
            <!-- Contenu spécifique à chaque page ici -->
