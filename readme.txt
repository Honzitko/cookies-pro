=== futuri Cookies ===
Verze: 1.0.0
Vyžaduje: WordPress 5.5+
Licence: GPL-2.0-or-later

Samostatná, GDPR-friendly cookie lišta pro futuri.cz. Všechny funkce zdarma,
plná kontrola, žádné externí závislosti a žádné "Powered by".

== Co plugin umí ==

* Cookie lišta (1. vrstva) s rovnocennými tlačítky Přijmout / Odmítnout
  (odmítnutí je stejně snadné jako souhlas — požadavek české i EU úpravy)
* Panel granulárního nastavení (2. vrstva) se 4 kategoriemi:
  Nezbytné (vždy zapnuté), Preferenční, Analytické, Marketingové
* Blokování skriptů spravovaných v sekci „Skripty a služby“ PŘED souhlasem —
  plugin je automaticky označí a aktivuje až po souhlasu s danou kategorií
* Google Consent Mode v2 — výchozí stav „denied“ a po volbě automatická
  aktualizace stavu souhlasu pro Google tagy (GA4 a Google Ads); nejde o
  univerzální blokátor libovolných externích skriptů
* Záznamy souhlasů do databáze s anonymizovanou IP + export do CSV
* Kompletní vizuální přizpůsobení (barvy, texty, kategorie, pozice, rozvržení)
* Plovoucí tlačítko + shortcode pro znovuotevření nastavení
* Bez načítání Google Fonts z CDN — plugin sám neodesílá data návštěvníka ven
* Přístupnost: viditelný focus, ovládání klávesnicí, respekt k reduced-motion,
  responzivní na mobilu

== Instalace ==

1. V administraci WordPressu jděte na Pluginy → Přidat nový → Nahrát plugin.
2. Vyberte soubor futuri-cookies.zip a klikněte Instalovat.
3. Aktivujte plugin.
4. V levém menu se objeví "futuri Cookies" — otevřete a nastavte podle sebe.

== Nastavení sledovacích skriptů ==

Google Analytics 4 / Google Ads:
  - V záložce Obecné nechte zapnutý "Google Consent Mode v2".
  - Svůj Google tag (gtag.js nebo GTM) načtěte běžným způsobem — např. přes
    téma, GTM, nebo vložením do hlavičky. Plugin předá Google tagům výchozí
    stav souhlasu "denied" a po volbě návštěvníka jej aktualizuje.
  - Consent Mode nastavuje stav souhlasu pro Google tagy; sám o sobě
    neblokuje načtení ani spuštění libovolného externího skriptu.

Ostatní služby (Facebook Pixel, Sklik, Hotjar…):
  - Záložka Skripty a služby → Přidat službu.
  - Vložte kód, vyberte kategorii (obvykle Marketingové) a pozici (head/body).
  - Plugin vložený kód automaticky změní na blokovaný skript a aktivuje jej
    teprve po souhlasu s danou kategorií.

Skripty přidané jiným pluginem/tématem (pokročilé):
  - Plugin cizí skripty automaticky nezachytává. Autor, který může upravit jejich
    výstup, je musí ručně označit: do <script> přidejte
    type="text/plain" a data-cookiecategory="analytics" (nebo functional /
    marketing).
  - U externího skriptu použijte data-src místo src, například:
    <script type="text/plain" data-cookiecategory="analytics"
    data-src="https://example.com/script.js"></script>
  - U vloženého JavaScriptu ponechte kód uvnitř takto označeného <script> tagu.
    Plugin takto ručně označený skript aktivuje po udělení souhlasu.

== Odkaz "Nastavení cookies" do patičky ==

Vložte shortcode:  [futuri_cookie_settings text="Nastavení cookies"]
nebo do vlastního odkazu použijte:  onclick="futuriCookies.open();return false;"

Doporučeno umístit do patičky vedle odkazu na Zásady ochrany osobních údajů.

== Veřejné JS API ==

  futuriCookies.open()        // otevře panel nastavení
  futuriCookies.accept()      // přijme vše
  futuriCookies.reject()      // odmítne vše
  futuriCookies.getConsent()  // vrátí aktuální stav souhlasu (objekt)
  futuriCookies.reset()       // smaže souhlas a znovu načte stránku (pro test)

== Poznámka k obsahu o dětech ==

Plugin řeší technickou stránku souhlasu. Právní texty (Zásady ochrany osobních
údajů, souhlas se zpracováním údajů dětí a s fotografiemi z akcí) doporučujeme
před ostrým spuštěním nechat zkontrolovat právníkem — zpracování údajů o dětech
má v GDPR přísnější výklad.
