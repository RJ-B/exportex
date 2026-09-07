/* Výchozí je světlý režim — atribut data-theme="light" je rovnou v <html>,
   takže platí i bez JavaScriptu a nemusí se na nic čekat. Tenhle skript ho
   jen odebere, když si návštěvník dříve zvolil tmavý. Běží v <head> před
   vykreslením, aby tmavý režim neproblikl světle.
   Samostatný soubor (ne inline skript) kvůli přísné CSP bez 'unsafe-inline'. */
try {
  if (localStorage.getItem('exportex-theme') === 'dark') {
    document.documentElement.removeAttribute('data-theme');
  }
} catch (e) {}
