/* ==========================================================================
   NASTAVENÍ POPTÁVKOVÉHO FORMULÁŘE

   ⚠ DŮLEŽITÉ K BEZPEČNOSTI
   Tohle je statický web na GitHub Pages. Nemá server, a tedy ani .env —
   všechno, co si prohlížeč načte, je veřejné. Cokoliv sem napíšete, si může
   kdokoliv přečíst ve zdrojovém kódu stránky. Proto sem NIKDY nepatří
   heslo ke schránce, SMTP údaje ani jiné skutečné tajemství.

   Nic takového tu naštěstí není potřeba: web maily neodesílá sám. Formulář
   pošle data službě, která z nich udělá e-mail a doručí ho na adresu níže.
   Přihlašovací údaje ke schránce k tomu nejsou třeba — schránka jen přijímá.

   Adresa příjemce (a případný klíč služby) jsou veřejné záměrně; tak jsou
   tyhle služby postavené. Nechrání se utajením, ale nastavením na straně
   služby: omezením na vlastní doménu a ochranou proti spamu. Kdyby někdy
   bylo potřeba skutečné tajemství, musel by web dostat malou serverovou
   funkci (Cloudflare Worker apod.) — postup je v NASAZENI.md.
   ========================================================================== */

window.EXPORTEX_CONFIG = {
  form: {
    /* FormSubmit — bez registrace i bez klíče, stačí cílový e-mail.
       AKTIVACE: po nasazení odešlete formulář jednou nanečisto. Na adresu
       níže přijde od FormSubmit potvrzovací e-mail — kliknutím na odkaz se
       formulář zapne. Do té doby se zprávy nedoručují. Stačí udělat jednou. */
    provider:  'formsubmit',
    recipient: 'mikyska@exportex.cz',
    endpoint:  'https://formsubmit.co/ajax/mikyska@exportex.cz',

    /* Volitelně: po aktivaci nabídne FormSubmit náhradní „alias" adresu
       (https://formsubmit.co/ajax/xxxxxxxxxxxx). Když ji sem doplníte místo
       adresy výše, zmizí e-mail ze zdrojového kódu a hůř se sbírá roboty. */

    accessKey: '',   // používá jen Web3Forms, viz alternativy v NASAZENI.md
    subject:   'Nová poptávka z webu Exportex'
  }
};
