# Plán: MCP server pre Bug Catcher

## Stav implementácie

| # | Celok | Stav |
|---|---|---|
| 1 | Závislosti (`symfony/mcp-bundle`, `nyholm/psr7`) + wiring test kernelu | ✅ hotové |
| 2 | `StackTraceParser` (extrakcia z Twig komponentu) + `StackTraceFormatter` | ✅ hotové |
| 3 | `RecordFinder` — read servis (search / detail / história) | ✅ hotové |
| 4 | `McpAccessTokenHandler` + firewall `mcp` | ✅ hotové |
| 5 | Tool `list_projects` | ✅ hotové |
| 6 | Tools `search_records`, `get_record_detail`, `set_record_status` | ✅ hotové |
| 7 | Funkčné testy — JSON-RPC cez HTTP | ⬜ todo |
| 8 | Recipe súbory + `docs/mcp.md` | ✅ hotové |

Denník (čo bolo spravené a čo nie) je na konci dokumentu.

## Kontext a cieľ

Bug Catcher beží ako samostatná Symfony aplikácia na serveri a zbiera chyby z klientskych
aplikácií. Cieľom je pridať do bundle **MCP server**, aby sa lokálne AI (Claude Code a pod.)
vedelo pripojiť na nasadenú inštanciu cez HTTP a:

- vylistovať projekty,
- nájsť aktívne (nevyriešené) chyby — filtrovateľné podľa **projektu**, **error kódu**, **levelu** a **času**,
- prečítať si detail chyby (message, request URI, stack trace, história výskytov),
- označiť chybu ako opravenú / archivovanú po tom, čo ju v projekte opraví.

## Voľba balíčka

**`symfony/mcp-bundle`** (aktuálne v0.13.x, súčasť monorepa `symfony/ai`) — oficiálny Symfony
bundle postavený na oficiálnom PHP SDK **`mcp/sdk`** (spravuje PHP Foundation + Symfony,
maintaineri Ch. Hertel, K. Obikwelu, T. Nyholm). Podporuje tools/prompts/resources cez HTTP aj
STDIO transport.

Kompatibilita overená:

| | vyžaduje | projekt má |
|---|---|---|
| `symfony/mcp-bundle` | Symfony `^7.3\|^8.0` | Symfony `^7.4` ✅ |
| `mcp/sdk` | PHP `^8.1` | PHP `>=8.3` ✅ |

Pozn.: bundle je označený ako *experimental* (mimo BC promise) → v `composer.json` pinneme na
`"symfony/mcp-bundle": "^0.13"`.

## Architektúra

```
Lokálne AI (Claude Code)
   │  HTTP  POST https://bugcatcher.example.com/mcp
   │  Authorization: Bearer <MCP_ACCESS_TOKEN>
   ▼
firewall "mcp" (access_token authenticator)
   ▼
symfony/mcp-bundle (JSON-RPC handler, routing type: mcp)
   ▼
BugCatcher\Mcp\Tool\*  (#[McpTool] atribúty)
   ▼
RecordFinder / RecordRepository / BatchRecordDelete...
```

Nové súbory pôjdu do `src/Mcp/` — `config/services.php` autowiruje celý namespace `BugCatcher\`,
takže tools sa zaregistrujú automaticky; mcp-bundle ich nájde cez `registry: tools: ['BugCatcher\Mcp\']`.

## MCP tools (kontrakt)

### 1. `list_projects`
Bez parametrov. Vráti enabled projekty: `code`, `name`, `url`.
(`ProjectRepository->findBy(['enabled' => true])`.)

### 2. `search_records`
Parametre (všetky voliteľné okrem limitu s defaultom):

| param | typ | default | poznámka |
|---|---|---|---|
| `projectCode` | string | — | mapuje sa na `Project` cez `code` (ako `LogRecordSaveProcessor`) |
| `status` | string | `new` | prefix match ako dashboard (`new`, `resolved`, `archived`, `withheld`) |
| `code` | string | — | `record.code = :code` (error kód) |
| `minLevel` | int | — | monolog level, join na `record_log.level` |
| `dateFrom` / `dateTo` | ISO 8601 string | — | na `record.date` (kryté indexom `date_idx`/`full_idx`) |
| `limit` | int | 25 | max 100 |

Výstup: pole záznamov zoskupených podľa `hash` — `id`, `type` (discr), `date`,
`firstOccurrence`, `count`, `level`, `message`, `code`, `requestUri`, `status`, `hasStackTrace`.

**Dôležité (výkon):** `Record` má JOINED dedičnosť — každé DQL nad ním LEFT JOINuje všetky
subtypy. Prevziať dvojkrokový pattern z `src/Twig/Components/LogList.php::init()`:
najprv len id-čka s `Query::HINT_FORCE_PARTIAL_LOAD`, potom hydratácia podľa id.
`count`/`firstOccurrence` **nie sú stĺpce** — dopočítavajú sa v PHP groupovaním podľa `getHash()`
(rovnako ako dashboard). Binary UUID: project param bindovať ako `->getId()->toBinary()`.

### 3. `get_record_detail`
Param: `recordId` (UUID). Vráti plný detail:
- message, level, requestUri, metadata, code, status, projekt;
- **stack trace** — `RecordLogTrace::stackTrace` je `serialize()`-nuté pole
  `Kregel\ExceptionProbe\Codeframe` objektov (viď `src/Twig/Components/Detail/StackTrace.php`),
  sformátovať na čitateľný text `file:line` + code frame;
- históriu výskytov — pattern z `src/Twig/Components/Detail/HistoryList.php::getHistory()`
  (`findBy(['hash' => ..], ['date' => 'DESC'], 50)` + group podľa timestampu).

### 4. `set_record_status`
Parametre: `recordId`, `status` ∈ **whitelist `resolved|archived`** — povinné, lebo
`RecordRepository::getUpdateStatusQB()` interpoluje status priamo do DQL (SQL-injection risk,
v UI to chráni len route requirement). Logika = replika
`src/Controller/RecordStatusController.php::changeStatus()`:
nájsť najstarší záznam s rovnakým `hash`+`status`, zavolať
`RecordRepositoryInterface::setStatus($record, $oldest->getDate(), $status, $record->getStatus(), true)`
— vyčistí celú hash skupinu a spustí notifikačnú pipeline (`RecordEvent`).

⚠️ Pri `resolved` sa so zapnutým `clear_stacktrace_on_fixed` (default true) **maže stack trace**
(`RecordLogTraceRepository::updateQb()`). Do description toolu napísať, nech AI číta detail
pred označením za fixed.

## Autentifikácia

Dnes je celé `^/api` `PUBLIC_ACCESS` bez akéhokoľvek autentikátora — MCP endpoint tak nechať
nemôžeme (čítal by chyby všetkých projektov ktokoľvek).

**Fáza 1 (tento plán): statický Bearer token**
- nový config kľúč `bug_catcher.mcp.access_token` v `config/definition.php`
  (default `%env(MCP_ACCESS_TOKEN)%`),
- `src/Security/McpAccessTokenHandler.php` implementujúci `AccessTokenHandlerInterface`,
  porovnanie cez `hash_equals()`; token injektnutý v `BugCatcherBundle::loadExtension()`,
- v recipe `config/recipes/packages/security.yaml` nový firewall:
  ```yaml
  mcp:
      pattern: ^/mcp
      stateless: true
      access_token:
          token_handler: BugCatcher\Security\McpAccessTokenHandler
  ```
  a `access_control: { path: ^/mcp, roles: ROLE_MCP }` (rola pridaná do hierarchie pod DEVELOPER).

**Fáza 2 (neskôr, mimo scope):** tokeny viazané na `User` entitu + scoping cez
`User::getActiveProjects()`, aby token videl len svoje projekty.

## Zmeny v repozitári (súbor po súbore)

1. **`composer.json`** — pridať `"symfony/mcp-bundle": "^0.13"`.
2. **`src/Mcp/Tool/ProjectTools.php`** — `list_projects`.
3. **`src/Mcp/Tool/RecordTools.php`** — `search_records`, `get_record_detail`, `set_record_status`.
4. **`src/Mcp/RecordFinder.php`** — read-query servis (dvojkrokový id-first pattern, group by hash);
   dnes žiadne read metódy neexistujú (`RecordRepositoryInterface` je write-only).
5. **`src/Mcp/StackTraceFormatter.php`** — unserialize `Codeframe[]` → text (logika podľa
   Twig komponentu `Detail\StackTrace`).
6. **`src/Security/McpAccessTokenHandler.php`** — Bearer token handler.
7. **`config/definition.php`** — nový node `mcp` (`enabled`, `access_token`, `max_limit`).
8. **`src/BugCatcherBundle.php::loadExtension()`** — inject configu do handlera/toolov
   (rovnaký pattern ako `DashboardController`).
9. **Recipe súbory pre downstream skeleton** (rovnako ako existujúce v `config/recipes/`):
   - `config/recipes/packages/mcp.yaml`:
     ```yaml
     mcp:
         servers:
             bug_catcher:
                 name: 'bug-catcher'
                 transports: { http: true }
                 http: { path: /mcp }
                 registry:
                     tools: ['BugCatcher\Mcp\']
                 session: { store: cache }
     ```
   - `config/recipes/routes/mcp.yaml` — `mcp: { resource: ., type: mcp }`
   - update `config/recipes/packages/security.yaml` (firewall vyššie).
10. **`docs/mcp.md`** — dokumentácia: nasadenie, env `MCP_ACCESS_TOKEN`, pripojenie klienta:
    ```bash
    claude mcp add --transport http bug-catcher https://bugcatcher.example.com/mcp \
        --header "Authorization: Bearer <token>"
    ```

## Testy

Test kernel: zaregistrovať `Mcp\Bundle\McpBundle` v `tests/App/config/bundles.php`,
pridať `tests/App/config/packages/mcp.yaml` + mcp routing, firewall do test `security.yaml`.

- **`tests/Functional/Mcp/McpToolsTest.php`** — cez zenstruck browser POST JSON-RPC na `/mcp`
  (`initialize` → `tools/call`), fixtures cez `ProjectFactory`/`RecordLogFactory`/
  `RecordLogTraceFactory`; scenáre: filter podľa projektu / kódu / času, detail s trace,
  `set_record_status` (over aj to, že nepovolený status hodí chybu), request bez tokenu → 401.
- **`tests/Unit/Mcp/`** — `StackTraceFormatter`, validácia filtrov/limitov.

## Overenie (end-to-end)

1. `composer require symfony/mcp-bundle` + `composer run phpunit`.
2. `php bin/console debug:mcp` — musí ukázať 4 tools s korektnými schémami.
3. Lokálne: `npx @modelcontextprotocol/inspector` proti `http://localhost/mcp` s Bearer tokenom —
   preklikať tools.
4. Reálny test: `claude mcp add ...` na lokálnu inštanciu, nechať AI nájsť a „opraviť" seedovanú chybu.

## Riziká / poznámky

- `symfony/mcp-bundle` je experimental → pin minor verzie, sledovať changelog `symfony/ai`.
- Filter podľa `level` joinuje `record_log` bez indexu — OK pri `limit ≤ 100`, neriešiť teraz.
- `zenstruck/browser` je už v `require`, funkčné testy nepotrebujú nové dev závislosti.
- STDIO transport neriešime — server beží vzdialene, HTTP stačí.

---

## Opravy plánu po overení vo v0.13.0

Plán bol písaný pred overením API. Reálny stav (overené proti zdrojákom
`symfony/mcp-bundle` v0.13.0 a `mcp/sdk` v0.8.1):

1. **Chýbajúca závislosť.** Bundle si PSR-17 factory *discoveruje*, neregistruje. Projekt nemá
   žiadnu PSR-7/17 implementáciu → bez `nyholm/psr7` HTTP transport spadne až za behu.
2. **`allowed_hosts`.** Default = iba `localhost` (ochrana proti DNS rebindingu). Na nasadenej
   inštancii treba v recipe vyplniť doménu, inak server odmietne každý request. Toto plán neriešil.
3. **Default cesta je `/mcp/<názov servera>`,** nie `/mcp` — treba explicitné `http: { path: /mcp }`.
4. **Chyby v tooloch:** hádzať `Mcp\Exception\ToolCallException`, tá jediná sa dostane ku klientovi
   ako `isError` + text. Hocijaká iná výnimka skončí ako generické `-32603` a správa sa stratí.
5. **Atribúty:** `Mcp\Capability\Attribute\McpTool` (na metódu/triedu) + `Mcp\Capability\Attribute\Schema`
   na parametre pre popisy. `outputSchema` sa *neodvodzuje*, iba sa zadáva ručne.
6. **Session store:** default `file` v `%kernel.cache_dir%/mcp-sessions/<názov>` — netreba cache pool.
7. Doťahuje sa `symfony/psr-http-message-bridge v8.1.0` (Symfony 8 komponent vedľa 7.4 stacku) —
   povolené jeho constraintmi, ale treba o tom vedieť.

## Denník

### Celok 8 — recipe + dokumentácia (hotové)

**Spravené:**
- `config/recipes/packages/mcp.yaml`, `config/recipes/routes/mcp.yaml`, firewall `mcp` +
  `access_control` + `ROLE_MCP` v `config/recipes/packages/security.yaml`.
- `docs/mcp.md` + odkaz z `README.md`.
- `allowed_hosts: '%env(csv:MCP_ALLOWED_HOSTS)%'` — overené, že to prejde validáciou configu
  (`variableNode` s `validate()` na poli); nenastavená premenná padne nahlas pri boote, čo je
  lepšie ako tiché 403 na každom requeste.

### Overenie na živej inštancii (bug-catcher-sentinel, https://127.0.0.1:8012)

Bundle je tam symlinknutý cez path repozitár. `composer update php-bug-catcher/bug-catcher`
dotiahol `symfony/mcp-bundle`, `mcp/sdk` aj `nyholm/psr7`, Flex zaregistroval `McpBundle`
(config súbory recipe nevytvoril, skopírované ručne).

Overené proti reálnym dátam:
- `php bin/console debug:mcp` → 4 tools, popisy natiahnuté z docblockov.
- `debug:router` → `_mcp_endpoint_bug_catcher  GET|POST|DELETE|OPTIONS  /mcp`.
- POST `/mcp` bez tokenu → **401**; so správnym tokenom → `initialize` prejde, vráti
  `Mcp-Session-Id` aj `instructions`.
- `tools/list` → 4 tools so správnymi schémami argumentov.
- `list_projects` → reálne projekty.
- `search_records` → zoskupené chyby, `count` / `firstOccurrence` sedia.
- `get_record_detail` → stack trace sa vykreslí presne ako sa čakalo: skrátené cesty
  (`/src/Controller/SecurityController.php:37`), kód okolo a značka `>` na nahlásenom riadku.
- Chybové cesty: neznámy projekt, nečitateľný dátum, nevalidné UUID → `isError: true` so správou.
  `limit: 5000` a `status: "deleted"` odmietne už JSON schéma na úrovni SDK (`-32602`), PHP
  kontrola ostáva ako druhá vrstva.
- `claude mcp add --transport http bug-catcher https://127.0.0.1:8012/mcp --header "Authorization: Bearer ..."`
  → `claude mcp list` hlási **✔ Connected**, čiže reálny MCP klient (vrátane self-signed certu)
  sa pripojí.

**Neoverené naživo:** `set_record_status`. Je to jediná zapisujúca operácia a inštancia beží nad
databázou s reálnymi chybami — označenie záznamu za `resolved` navyše pri
`clear_stacktrace_on_fixed: true` **zmaže stack trace nenávratne**. Čaká na súhlas.
Integračné testy ju pokrývajú vrátane toho, že sa vyčistí celá hash skupina.

### Celok 6 — record tools (hotové)

**Spravené:**
- `src/Mcp/Tool/RecordTools.php` — `search_records`, `get_record_detail`, `set_record_status`.
- `RecordFinder::find()` — dohľadanie záznamu podľa id, vracia konkrétnu podtriedu, aby sa dal
  odovzdať repozitáru, ktorý o nej vie (`RecordLogTraceRepository` maže stack trace).
- Testy: `tests/Integration/Mcp/RecordToolsTest.php` (18), `McpRegistrationTest` rozšírený na
  všetky štyri tools.

**Chybové stavy:** všetko odchádza ako `ToolCallException` — je to jediná výnimka, ktorú SDK
premení na chybový výsledok so *správou*. Čokoľvek iné skončí ako holé „Error while executing
tool“ a klient sa nedozvie nič. Kryté: neznámy `projectCode` (so pokynom zavolať `list_projects`),
nečitateľný dátum, limit mimo rozsahu, nevalidné UUID, neexistujúci záznam, zakázaný status.

**Whitelist statusov je bezpečnostná vec, nie kozmetika.** `RecordRepository::getUpdateStatusQB()`
vkladá status priamo do DQL (`->set('l.status', "'{$newStatus}'")`). `#[Schema(enum: ...)]` síce
validuje na strane SDK, ale kontrola je zopakovaná aj v PHP — schéma je popis pre klienta, nie
bezpečnostná hranica. Test `testAStatusOutsideTheWhitelistIsRefused` skúša aj reťazec
s uvodzovkou, ktorý by inak prepísal `WHERE`.

**Formát dátumov:** `Y-m-d H:i:s` bez zóny. Stĺpec v DB zónu nenesie, takže pripísať mu `+00:00`
by bola vymyslená informácia — a testy by záviseli od `date.timezone` stroja. Sú to tie isté časy,
aké ukazuje dashboard.

**Nespravené / vedomé rozhodnutia:**
- `get_record_detail` nemá stránkovanie histórie — vracia posledných 50 výskytov ako dashboard.
- `search_records` nevracia `metadata` (len detail). V zozname by to bol šum.
- Tools nekontrolujú role samy; stačí `access_control` na `^/mcp`. Keď fáza 2 naviaže token na
  `User`, bude treba doplniť scoping na jeho projekty — dovtedy token vidí všetky.

### Celok 3 — `RecordFinder` (hotové)

**Spravené:**
- `src/Mcp/RecordSearchCriteria.php` — VO namiesto metódy so siedmimi voliteľnými parametrami.
  Validuje limit (1..100) a poradie dátumov, takže nezmyselný vstup padne skôr, ako sa dostane do DQL.
- `src/Mcp/RecordFinder.php` — `search()` + `history()`.
- Testy: `tests/Integration/Mcp/RecordFinderTest.php` (17).

**Odchýlka od plánu — grouping v SQL, nie v PHP.**
Plán hovoril prevziať dvojkrokový pattern z `LogList::init()` (vytiahnuť N riadkov, zoskupiť v PHP).
Nepoužil som ho, lebo tam `count` znamená „koľko výskytov sa zmestilo do okna“, nie „koľko ich je“.
Dashboard si to môže dovoliť — človek vidí zoznam. AI ale podľa `count` **prioritizuje**, takže
okno by ho systematicky klamalo. Namiesto toho:
1. `GROUP BY hash` s `COUNT/MIN/MAX` a `LIMIT` → skutočný počet a rozsah, a `limit` naozaj znamená
   „počet rôznych chýb“, nie „počet riadkov“.
2. Dohydratovanie reprezentanta cez `hash IN (...) AND date IN (...)` → pár riadkov na skupinu,
   nie celá história.

**Odchýlka od plánu — dotazy stoja na `RecordLog`, nie na `Record`.**
Tým odpadá `INSTANCE OF` aj `HINT_FORCE_PARTIAL_LOAD` a sprístupní sa `level`/`message`/`requestUri`
(na `Record` neexistujú). `RecordPing` vypadne sám — je to výsledok pingu, nie chyba v kóde.
**Dôsledok:** vlastné typy záznamov, ktoré dedia priamo z `Record` a nie z `RecordLog`, MCP tools
neuvidia. Pre `RecordCron` z testov to platí tiež. Zámerné — tools sľubujú `message` a `level`,
ktoré takáto trieda nemusí mať.

**Opravené popri tom:** `tests/App/config/doctrine/BugCatcherBundle/Record.orm.xml` bola zastaraná
kópia — chýbali polia `code` a `metadata` aj index `code_idx`. Testovacia aplikácia teda bežala nad
schémou, akú žiadne nasadenie nemá, a `SendRecordTest::testMetadata` ani
`CronRecordTest::testSendPlainRecordWithCode` to neodhalili — overujú len HTTP 201, nie že sa
hodnota uložila. Zosúladené s `config/doctrine/Record.orm.xml`.

**Pozor (zachytené testom):** `record.project = :project` s entitou ani s `Uuid` objektom nesedí na
nič — stĺpec je binárny. Bindovať treba `->getId()->toBinary()`, ako to robí `LogList`.

**Nespravené:**
- `bug_catcher.mcp.max_limit` ako config kľúč (plán ho spomínal). Limit je zatiaľ konštanta
  `RecordSearchCriteria::MAX_LIMIT`. Konfigurovateľné to nemá kto potrebovať, kým sa neukáže dopyt.
- Filter podľa `level` stále nemá index (`record_log.level`) — pri `limit ≤ 100` netreba riešiť.

### Celok 4 — autentifikácia (hotové)

**Spravené:**
- `src/Security/McpAccessTokenHandler.php` — porovnanie cez `hash_equals()`.
  **Fail closed:** nenastavený alebo prázdny token odmieta *každý* request. Opačné správanie
  („nič nie je nastavené, takže všetko sedí“) by zverejnilo chyby všetkých projektov v deň, keď
  niekto zabudne na `MCP_ACCESS_TOKEN`.
- `UserBadge` si nesie vlastného `InMemoryUser` s rolou `ROLE_MCP`, takže firewall nepotrebuje
  user providera. Identifikátor je fixný (`mcp`), nie odvodený od tokenu — až fáza 2 ho naviaže
  na `User` a oscopuje na `getActiveProjects()`.
- `config/definition.php` → nový node `bug_catcher.mcp.access_token`, default
  `%env(default::MCP_ACCESS_TOKEN)%` (nenastavená premenná = `null`, nie pád pri boote).
- Injekcia v `BugCatcherBundle::loadExtension()`, firewall + `access_control` + `ROLE_MCP:
  ROLE_DEVELOPER` v `tests/App/config/packages/security.yaml`.
- Testy: `tests/Unit/Security/McpAccessTokenHandlerTest.php` (9, vrátane prefixu tokenu a
  nenakonfigurovaného servera), `tests/Functional/Mcp/McpAccessTest.php` (3 — bez tokenu 401,
  zlý token 401, správny token prejde).
- `tests/Functional/Mcp/McpJsonRpc.php` — trait s JSON-RPC handshakom, aby ho celok 7 nemusel
  opakovať v každom teste.

**Nespravené:**
- Firewall v `config/recipes/packages/security.yaml` pre downstream skeleton — celok 8. Zatiaľ to
  nie je diera: recipe ešte nepridáva ani `mcp` routing, takže v skeletone endpoint neexistuje.
- Rate limiting na endpointe. `symfony/rate-limiter` je v závislostiach, ale statický token bez
  throttlingu je stále brute-forcovateľný — treba zvážiť, nie je to však v zadaní.

### Celok 1 + 5 — bundle beží, `list_projects` (hotové)

Spojené do jedného kroku zámerne: `registry` pattern, ktorý nesedí na žiadnu službu, hodí
`LogicException` pri kompilácii kontajnera, takže bundle sa nedá nakonfigurovať skôr, ako existuje
prvý tool.

**Spravené:**
- `composer require symfony/mcp-bundle:^0.13 nyholm/psr7:^1.8` (17 nových balíkov).
- `src/Mcp/Tool/ProjectTools.php` — `list_projects`, len enabled projekty, zoradené podľa `code`
  (stabilné poradie medzi volaniami).
- Test app: `tests/App/config/bundles.php`, `packages/mcp.yaml`, `routes/mcp.yaml`.
- `tests/Integration/Mcp/ProjectToolsTest.php` (4), `McpRegistrationTest.php` (2).

**Overená obava — funguje `#[McpTool]` vnútri bundle?**
Áno. Bundle zbiera tools cez tag `mcp.tool`, ktorý pridáva `registerAttributeForAutoconfiguration`,
a ten sa aplikuje iba na služby s `autoconfigure()`. `config/services.php` načítava celý namespace
`BugCatcher\` s `->autowire()->autoconfigure()`, takže tag sadne. Overené v skompilovanom
kontajneri:
`$instance->addTool(['BugCatcher\Mcp\Tool\ProjectTools', 'listProjects'], 'list_projects', ...)`.
Nič sa ručne wirovať nemusí. `McpRegistrationTest` to drží zafixované, lebo keby niekto z
`services.php` odstránil `autoconfigure()`, tools by zmizli potichu.

**Pozor pri čítaní registry v testoch:** `mcp.server.<name>.registry` je po boote prázdna. Tools
zozbierané pri kompilácii sedia na *builderi* a do registry sa dostanú až keď builder poskladá
server (`$container->get('mcp.server.bug_catcher')`).

**Nespravené:**
- Recipe súbory pre downstream skeleton (`config/recipes/packages/mcp.yaml`, `routes/mcp.yaml`) —
  patria do celku 8, zatiaľ je nakonfigurovaná len testovacia aplikácia.
- `allowed_hosts` je zatiaľ nechané na SDK defaulte (iba localhost), čo testom stačí. Nasadená
  inštancia si musí doplniť vlastnú doménu — rieši celok 8.

### Celok 2 — stack trace (hotové)

**Spravené:**
- `src/Service/StackTrace/StackTraceParser.php` — unserialize + skracovanie ciest. Logika
  *presunutá* z `Twig\Components\Detail\StackTrace` (nie skopírovaná), komponent ju teraz volá.
- `src/Service/StackTrace/MalformedStackTraceException.php` — nečitateľný payload je výnimka,
  nie `false`/prázdne pole. Každý konzument sa rozhodne sám, ako to zobrazí.
- **Bezpečnostná oprava popri tom:** `unserialize()` je teraz obmedzený cez
  `allowed_classes: [Codeframe::class]`. Payload chodí z klientskych aplikácií cez verejné API,
  takže obnovovanie ľubovoľných tried z neho bola polovica PHP object-injection reťazca.
  Pôvodný `try/catch (Exception)` navyše nefungoval — `unserialize()` na smetiach nehádže výnimku,
  vracia `false`, takže mimo test/dev error handlera by nasledoval `TypeError` vo `foreach`.
- `src/Mcp/StackTraceFormatter.php` — `Codeframe[]` → text (`#0 file:line  volanie` + kód so
  značkou `>` na nahlásenom riadku).
- Testy: `tests/Unit/Service/StackTrace/StackTraceParserTest.php` (11),
  `tests/Unit/Mcp/StackTraceFormatterTest.php` (7). Existujúcich 10 testov Twig komponentu
  ostalo zelených → refaktor je behaviour-preserving.

**Nespravené / vedomé rozhodnutia:**
- Formatter needoberá počet rámcov ani riadkov kódu. Jeden záznam = jeden trace, typicky <50
  rámcov; tiché orezávanie by čitateľovi zamlčalo časť stopy. Ak sa ukáže ako problém, rieši sa
  limitom až v `get_record_detail`.
- Správanie pri jedinom rámci (skráti sa na holý názov súboru) je zachované z pôvodného kódu,
  hoci je diskutabilné — extrakcia nemala meniť správanie. Pokryté testom, aby bolo vidno, že je
  to vedomé.