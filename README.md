# futuri Cookies

WordPress plugin pro cookie lištu se granulárními souhlasy, blokováním
spravovaných skriptů a podporou Google Consent Mode v2.

## Testování

Testovací základ je záměrně lehký: čisté PHP pomocné funkce se spouštějí přes
PHPUnit s malou kompatibilní vrstvou WordPressu a kontrola obnovení Consent Mode
cookie běží v integrovaném test runneru Node.js. Testy proto nevyžadují lokální
instalaci WordPressu ani databázi.

### Požadavky

* PHP 7.4 nebo novější
* [Composer](https://getcomposer.org/)
* Node.js 18 nebo novější (pro test JavaScriptu bez externích balíčků)

### Instalace závislostí

```bash
composer install
```

### Spuštění

Po instalaci závislostí spustí celý test suite jediný příkaz:

```bash
composer test
```

Příkaz spustí PHP unit testy i JavaScriptový regresní test. Ověřují
anonymizaci IPv4/IPv6 adres, validaci a stabilní normalizaci consent dat,
transformaci blokovaných `<script>` tagů a ignorování consent cookie vytvořené
neaktuální verzí pluginu.
