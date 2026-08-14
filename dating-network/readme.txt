=== Dating Network ===
Contributors: alexandervandijl
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 0.6.0
License: GPLv2 or later

Gratis datingplatform voor 18+ singles met wederzijdse man-vrouw matching, interne chat en uitlegbare matchscore.

== 0.6.0 ==
* Bij “Melden & ophangen” kan één stilstaand bewijsscreenshot van de remote video worden vastgelegd; er is geen continue opname.
* Gebruikers worden vóór het videobellen expliciet geïnformeerd over deze rapport-triggered veiligheidsfunctie.
* Alleen het beeld van de gemelde match wordt vastgelegd; de eigen camera van de melder wordt niet als bewijs opgeslagen.
* Bewijs wordt in een afgeschermde databasetabel opgeslagen en is alleen zichtbaar voor beheerders met manage_options.
* Nieuwe beheerpagina Dating Network > Videobewijs met rapport, melder, gemelde gebruiker, match en integriteitshash.
* Screenshots worden standaard 30 dagen na koppeling aan een melding verwijderd; afgebroken/onvoltooide bewijscaptures worden al na 15 minuten verwijderd.
* Eén gebruikersmelding leidt niet automatisch tot een platformban; beheer beoordeelt screenshot, melding, eerdere signalen en context.
* Persoonlijk blokkeren door de melder blijft mogelijk en is geen platformbrede sanctie.

== 0.5.9 ==
* Videoveiligheidslaag toegevoegd bovenop het eigen P2P-videobellen.
* Voor ieder videogesprek staat expliciet dat naakt, seksueel expliciet gedrag en seksuele handelingen voor de camera niet zijn toegestaan.
* Nieuwe knop “Melden & ophangen” beëindigt het gesprek direct en opent een veiligheidsmelding.
* Videomeldingen ondersteunen redenen zoals naakt/expliciet beeld, ongewenst seksueel gedrag, intimidatie, druk zetten, bedreiging, nepidentiteit en twijfel over leeftijd.
* Melder kan de andere gebruiker direct voor zichzelf blokkeren; de match wordt dan beëindigd.
* Ernstige videomeldingen komen in het bestaande interne vertrouwens- en risicosysteem terecht.
* Nieuwe beheerpagina Dating Network > Videoveiligheid met videopogingen, camera-starts, succesvolle verbindingen, P2P-mislukkingen en videomeldingen.
* Videomeldingen kunnen door beheer als afgehandeld, onterecht of opnieuw open worden gemarkeerd.
* Technische call-events worden zonder video of audio opgeslagen; Dating Network neemt videogesprekken niet heimelijk op.

== 0.5.8 ==
* Externe videoprovider vervangen door eigen 1-op-1 WebRTC-videobellen.
* Audio/video gaat waar mogelijk rechtstreeks browser naar browser; WordPress verzorgt alleen tijdelijke signaling.
* Alleen de twee deelnemers van een actieve match kunnen de videosessie gebruiken.
* Eigen videobelinterface met microfoon uit, camera uit en ophangen.
* Publieke STUN kan voor de eerste test worden gebruikt zonder betaalde videodienst.
* Optionele eigen coturn TURN-fallback voorbereid voor netwerken waar directe P2P niet lukt.
* TURN shared secret blijft server-side en tijdelijke HMAC-credentials worden automatisch gegenereerd.
* Geen opname, transcriptie of externe videoplatform-account nodig.

== 0.5.7 ==
* Eerste videobelimplementatie via Cloudflare RealtimeKit; in 0.5.8 vervangen door het eigen WebRTC-systeem.

== 0.5.6 ==
* Nieuwe Eerste 100-groeimodule met teller op basis van complete actieve, match-klare profielen.
* Iedere ingelogde gebruiker krijgt een persoonlijke uitnodigingslink met WhatsApp-, Facebook- en kopieerknoppen.
* Persoonlijk overzicht toont unieke referralbezoekers, aanmeldingen en hoeveel uitgenodigde gebruikers match-klaar zijn geworden.
* Nieuwe beheerpagina Dating Network > Groei & statistieken met de volledige funnel van account naar e-mailverificatie, match-klaar profiel, foto, interesse, match, chat en iemand gevonden.
* Campagnes kunnen met ?dn_src= worden gemeten; speciale links voor Knipmodel Network en Facebook staan direct in het dashboard.
* Referral- en campagnebezoeken worden zonder opslag van ruwe IP-adressen gemeten en per bezoeker/campagne maximaal eenmaal per uur geteld.
* Dashboard toont aanmeldingen per bron en de beste uitnodigers.

== 0.5.5 ==
* Nieuwe Dating Network-branding met navy, berry, coral en blush als vaste kleuren.
* Nieuwe vector site-icon/favicon in dezelfde merkstijl.
* Homepage-positionering aangescherpt naar “Echte mensen. Echte connecties.”.
* Kernbelofte staat prominent op homepage en app-pagina's: Dating Network is gratis en blijft gratis.
* Profiel, matching, matches en interne chat blijven kosteloos; geen betaalde boosts, premium matches of betaalde voorrang.
* Eventuele losse coaching kan later optioneel bestaan, maar geeft nooit extra zichtbaarheid of betere matchkansen.
* App-header en footer zijn in dezelfde branding gebracht en tonen de gratis-belofte.
* Homepage loopt voortaan via de normale shortcodefilters, zodat homepage-modules zoals goedgekeurde profielfoto's betrouwbaar kunnen inhaken.
* Basis Open Graph metadata en themakleur toegevoegd voor consistente social sharing.

== 0.5.4 ==
* Chatmonitor toont per gebruiker en gesprek een interne groen-oranje-rood aandachtssignaal.
* Rood staat bovenaan, daarna oranje en daarna groen zodat beheer snel kan prioriteren.
* Indicator gebruikt open meldingen, verhoogde veiligheidsmeldingen, beheersignalen, waarschuwingen, ontvangen blokkades, positieve waarderingen en beheerstatus.
* Positieve waarderingen kunnen een normaal risicosignaal verlagen, maar overrulen geen beheerblokkade of serieuze open veiligheidsmeldingen.
* Chatmonitor kan worden gefilterd op rood, oranje of groen.
* Volledige chatweergave toont per deelnemer de redenen achter de interne indicatie en een directe link naar het vertrouwensdossier.
* De indicator is uitsluitend intern, niet openbaar, verandert de matchscore niet en neemt geen automatische sanctiebeslissingen.
* Groen betekent alleen dat er geen actuele interne aandachtssignalen zijn; het is geen veiligheidsgarantie of verificatiebadge.

== 0.5.3 ==
* Uitgebreid rapporteren vanuit chat met reden en vrije toelichting.
* Gebruikers kunnen een vervelende chatter voor zichzelf blokkeren; de chat stopt direct en beide profielen worden niet meer aan elkaar getoond.
* Nieuw beheeronderdeel Dating Network > Gebruikers & vertrouwen met een intern dossier per gebruiker.
* Beheerder kan positieve waarderingen registreren voor respectvol, betrouwbaar en constructief gedrag.
* Beheerder kan negatieve signalen registreren en waarschuwingen, pauzes of volledige blokkades uitvoeren.
* Positieve en negatieve signalen zijn niet publiek en beïnvloeden de matchscore niet automatisch.
* Gebruikers zien privé wanneer Dating Network hun goed gedrag positief heeft gewaardeerd.

== 0.5.2 ==
* Beheerders kunnen in de opstartfase alle Dating Network-chats monitoren via Dating Network > Chatmonitor.
* Chatmonitor toont deelnemers, status, aantal berichten, laatste activiteit, preview en volledige gesprekshistorie.
* Alleen WordPress-beheerders met manage_options krijgen toegang tot de monitor.
* Gebruikers krijgen in de chat een duidelijke melding dat bevoegde beheerders gesprekken in de opstartfase kunnen controleren voor veiligheid, misbruikpreventie en kwaliteitscontrole.
* Chatmonitoring kan later via een beheerinstelling worden uitgeschakeld.

== 0.5.1 ==
* Profielfoto upload met verplichte verklaring dat het een echte foto van de gebruiker zelf is.
* Verplichte verklaring dat de gebruiker rechthebbende is of de benodigde toestemming/licentie heeft voor gebruik en publicatie.
* Verplichte verklaring dat de foto geen logo, watermerk, URL, QR-code, contactgegevens, socialmedia-handle of andere promotie bevat.
* Iedere foto blijft in moderatie totdat een beheerder hem handmatig goedkeurt.
* Beheerder bevestigt bij goedkeuring zowel plausibele echtheid als afwezigheid van promotie.
* Homepage-publicatie is een aparte vrijwillige opt-in, heeft geen invloed op matching en kan later worden ingetrokken.
* Alleen goedgekeurde foto's van actieve profielen met homepage-toestemming verschijnen op de openbare homepage.
* Foto's worden bij accountverwijdering ook verwijderd.
* Afbeeldingen worden waar mogelijk opnieuw opgeslagen om EXIF/GPS-metadata te verwijderen.

== 0.5.0 ==
* Eigen Dating Network-layout op alle platformpagina's; geen standaard themafooter of themanavigatie meer.
* Vernieuwde Ontdek singles-onboarding wanneer een profiel nog niet match-klaar is.
* Aanmelden, inloggen, account, profiel, ontdekken, matches en chat in één consistente interface.
* Singles-only, wederzijdse voorkeuren, religie/levensovertuiging, kinderwens, leefstijl en 0–100 interesses.
* Geen externe links, telefoonnummers, e-mails, socials of promotieaccounts in interne communicatie.
* Wederzijdse interesse vereist voordat chat opent.
* GitHub-updater met SHA-256-verificatie.
