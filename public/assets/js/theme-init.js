/* Výchozí je světlý režim — atribut data-theme="light" je rovnou v <html>,
   takže platí i bez JavaScriptu a nemusí se na nic čekat. Tenhle skript ho
   jen odebere, když si návštěvník sám zvolil tmavý (klíč -v2, viz main.js).
   Běží v <head> před vykreslením, aby tmavý režim neproblikl světle.
   Samostatný soubor (ne inline skript) kvůli přísné CSP bez 'unsafe-inline'. */
try {
  if (localStorage.getItem('exportex-theme-v2') === 'dark') {
    document.documentElement.removeAttribute('data-theme');
  }
} catch (e) {}
