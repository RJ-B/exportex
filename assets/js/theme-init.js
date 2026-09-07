/* Nastaví uložený motiv ještě před prvním vykreslením — jinak by světlý
   režim po načtení probliknul tmavě. Samostatný soubor (ne inline skript),
   aby mohla platit přísná CSP bez 'unsafe-inline' pro skripty. */
try {
  if (localStorage.getItem('exportex-theme') === 'light') {
    document.documentElement.setAttribute('data-theme', 'light');
  }
} catch (e) {}
