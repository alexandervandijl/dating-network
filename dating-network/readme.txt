=== Dating Network ===
Contributors: alexandervandijl
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv2 or later

Veilige datingplugin voor singles, gericht op echte wederzijdse matches in plaats van engagement of betaalde zichtbaarheid.

== V1 ==
* Alleen 18+ singles.
* Man/vrouw-matching.
* E-mailverificatie met mogelijkheid om de verificatiemail opnieuw te sturen.
* Expliciete toestemming voor verwerking van dating-/matchgegevens en apart voor religie/levensovertuiging.
* Toestemming kan vanuit het profiel worden ingetrokken; het profiel wordt dan gepauzeerd en actieve matches worden gesloten.
* Profiel met woonplaats, relatiedoel, kinderen/kinderwens, leefstijl, religie en 0-100 interesses.
* Maximaal 5 favoriete interesses.
* Uitlegbare wederzijdse matchscore.
* Interesse tonen en alleen chatten na wederzijdse interesse.
* Interne chat blokkeert links, e-mailadressen, telefoonnummers, socialmedia-handles en veel externe platformverwijzingen.
* Blokkeren, rapporteren en reden "persoon is niet single".
* "Ik heb iemand gevonden" als expliciete successtatus; het profiel verdwijnt uit matching en actieve matches worden gesloten.
* Definitief account verwijderen vanuit de front-end.
* Basis-admin dashboard met succes-KPI en moderatiemeldingen.

== Installatie ==
1. Upload dating-network.zip via Plugins > Nieuwe plugin > Plugin uploaden.
2. Activeer de plugin.
3. De benodigde pagina's worden automatisch aangemaakt.
4. Ga naar Dating Network in wp-admin en stel de afzendernaam en het e-mailadres in.
5. Configureer betrouwbare SMTP-mailaflevering in WordPress/hosting.

== Belangrijke V1-beperking ==
De maximale afstand wordt al opgeslagen, maar V1 berekent nog geen exacte kilometers tussen woonplaatsen. Daarvoor moet later geocoding/lat-lng worden aangesloten. Profielfoto's worden als afbeeldingsbestand gevalideerd, maar V1 kan tekst/QR-codes in afbeeldingen nog niet automatisch herkennen; moderatie blijft daarvoor nodig.
