<?php

declare(strict_types=1);

namespace Database\Seeders\Content;

/**
 * Dutch translations of the placeholder pages the CMS seeder creates.
 *
 * Used by the seeder for new installs and by the migration that adds them to
 * existing stores - only for pages whose English body is still the seeded
 * placeholder, so a page someone has rewritten never gets a stale translation.
 */
final class DutchPages
{
    /**
     * @return array<string, array{title: string, excerpt: string, body: string}>
     */
    public static function all(): array
    {
        $notice = '<p><strong>Deze pagina bevat voorbeeldtekst.</strong> Vervang ze vóór de lancering '
            .'in het beheerpaneel onder Pagina&rsquo;s. Het is geen juridisch document en houdt '
            .'geen enkele toezegging namens jou in.</p>';

        return [
            'about' => [
                'title' => 'Over ons',
                'excerpt' => 'Wie we zijn, wat we verkopen en hoe we werken - voorbeeldtekst.',
                'body' => $notice
                    .'<p>We zijn een klein team dat alledaagse spullen kiest die we zelf zouden '
                    .'gebruiken, en die zonder gedoe bij je thuis bezorgt. Deze alinea laat zien hoe '
                    .'een introductie op deze pagina oogt; schrijf hier je eigen verhaal.</p>'
                    .'<h2>Wat we verkopen</h2>'
                    .'<p>Beschrijf hier je aanbod: de categorieën waarop je je richt, de materialen '
                    .'die je verkiest en het soort klant voor wie je inkoopt. Twee of drie zinnen '
                    .'zijn meestal genoeg voordat een bezoeker doorklikt naar de winkel.</p>'
                    .'<h2>Hoe we werken</h2>'
                    .'<ul>'
                    .'<li>We kopen in kleine partijen en vullen aan wat verkoopt.</li>'
                    .'<li>We vermelden bij elk product de echte afmetingen en materialen.</li>'
                    .'<li>We beantwoorden berichten zelf, tijdens de kantooruren.</li>'
                    .'</ul>'
                    .'<h2>Vragen</h2>'
                    .'<p>Verwijs mensen naar je contactpagina en vermeld ongeveer hoe lang een '
                    .'antwoord duurt. Beloof geen reactietijd die je niet kunt waarmaken.</p>',
            ],
            'contact' => [
                'title' => 'Contact',
                'excerpt' => 'Hoe je ons bereikt en wat je best in je bericht vermeldt.',
                'body' => $notice
                    .'<p>Gebruik deze pagina om mensen te vertellen hoe ze je het snelst bereiken. '
                    .'Het adres, e-mailadres en telefoonnummer in de footer komen uit je '
                    .'winkelinstellingen, dus vul ze daar in in plaats van ze twee keer te typen.</p>'
                    .'<h2>Voordat je schrijft</h2>'
                    .'<p>Als je dit vermeldt, bespaar je een extra bericht:</p>'
                    .'<ul>'
                    .'<li>Je bestelnummer, als het bericht over een bestelling gaat.</li>'
                    .'<li>De productnaam of SKU, als het over een artikel gaat.</li>'
                    .'<li>Een foto, als er iets beschadigd is aangekomen.</li>'
                    .'</ul>'
                    .'<h2>Openingsuren</h2>'
                    .'<p>Vermeld de dagen en uren waarop je berichten echt leest, en de tijdzone. '
                    .'Vervang deze alinea door je eigen tekst en laat elke uitspraak over '
                    .'reactietijden weg waar je niet zeker van bent.</p>',
            ],
            'privacy' => [
                'title' => 'Privacybeleid',
                'excerpt' => 'Welke gegevens deze winkel verzamelt en waarvoor ze worden gebruikt.',
                'body' => $notice
                    .'<p>Een privacybeleid moet beschrijven wat je winkel werkelijk doet. Dit is '
                    .'dus een geraamte om in te vullen - geen beleid dat je zomaar kunt overnemen. '
                    .'Laat het nakijken voordat je het publiceert.</p>'
                    .'<h2>Wat we verzamelen</h2>'
                    .'<p>Som op wat je werkelijk bewaart: de naam, het e-mailadres en het adres die '
                    .'nodig zijn om een bestelling te verzenden, de inhoud van die bestelling, en '
                    .'wat je analyse- en betaalproviders registreren. Kaartgegevens worden door je '
                    .'betaalprovider verwerkt en komen nooit bij deze winkel terecht.</p>'
                    .'<h2>Waarom we het verzamelen</h2>'
                    .'<ul>'
                    .'<li>Om een bestelling te laten betalen en te leveren.</li>'
                    .'<li>Om vragen over een bestelling te beantwoorden.</li>'
                    .'<li>Om aan boekhoudkundige en fiscale verplichtingen te voldoen.</li>'
                    .'</ul>'
                    .'<h2>Met wie het wordt gedeeld</h2>'
                    .'<p>Noem je betaalprovider, je vervoerder en elke andere verwerker die '
                    .'klantgegevens ziet, en vermeld wat elk van hen ontvangt.</p>'
                    .'<h2>Jouw keuzes</h2>'
                    .'<p>Leg uit hoe iemand een kopie van zijn gegevens opvraagt of vraagt om ze te '
                    .'verwijderen, en hoe lang je bestelgegevens bewaart.</p>',
            ],
            'terms' => [
                'title' => 'Algemene voorwaarden',
                'excerpt' => 'De voorwaarden die gelden als je bij deze winkel koopt.',
                'body' => $notice
                    .'<p>Deze kopjes zijn de gebruikelijke opbouw van een voorwaardenpagina. De '
                    .'tekst onder elk kopje moet overeenkomen met hoe je winkel echt werkt, en moet '
                    .'vóór de lancering door een deskundige worden nagekeken.</p>'
                    .'<h2>Gebruik van deze winkel</h2>'
                    .'<p>Vermeld wie een bestelling mag plaatsen, en wat er gebeurt met een account '
                    .'dat misbruik maakt van de winkel.</p>'
                    .'<h2>Bestellingen en prijzen</h2>'
                    .'<p>Leg uit wanneer een overeenkomst tot stand komt, wat er gebeurt als een '
                    .'artikel na het bestellen niet meer beschikbaar blijkt, en hoe je met een '
                    .'prijsfout omgaat. Prijzen en belastingen bij het afrekenen komen uit je '
                    .'winkelinstellingen.</p>'
                    .'<h2>Annuleringen en retouren</h2>'
                    .'<p>Vat je retourpagina hier samen en verwijs ernaar, in plaats van een '
                    .'tweede, net iets andere termijn te vermelden.</p>'
                    .'<h2>Aansprakelijkheid</h2>'
                    .'<p>Beschrijf de beperkingen die in jouw rechtsgebied gelden. Kopieer niet '
                    .'de clausule van een andere winkel: welke op jou van toepassing is, hangt af '
                    .'van waar je handel drijft.</p>',
            ],
            'shipping' => [
                'title' => 'Verzending',
                'excerpt' => 'Waarheen we verzenden, hoe snel we verzenden en hoe de tarieven worden berekend.',
                'body' => $notice
                    .'<p>Tarieven en leveringsopties bij het afrekenen komen uit je verzendzones en '
                    .'-tarieven in het beheerpaneel. Gebruik deze pagina om ze in woorden uit te '
                    .'leggen, en houd beide met elkaar in overeenstemming.</p>'
                    .'<h2>Waarheen we verzenden</h2>'
                    .'<p>Som de landen of regio&rsquo;s op waarheen je verzendt, en die waarheen je '
                    .'bewust niet verzendt. Als een zone eigen regels heeft - bijvoorbeeld een '
                    .'toeslag voor afgelegen gebieden - vermeld dat dan hier.</p>'
                    .'<h2>Verzendtermijnen</h2>'
                    .'<ul>'
                    .'<li>Bestellingen die op een werkdag vóór je deadline worden geplaatst, '
                    .'vertrekken dezelfde dag.</li>'
                    .'<li>Latere bestellingen, of bestellingen in het weekend, vertrekken de '
                    .'volgende werkdag.</li>'
                    .'<li>Feestdagen voegen een dag toe.</li>'
                    .'</ul>'
                    .'<h2>Geschatte levertijd</h2>'
                    .'<p>Geef per zone de eigen schatting van de vervoerder en vermeld dat het een '
                    .'schatting is. Een leverdatum waarover je geen controle hebt, mag niet als '
                    .'belofte worden geschreven.</p>'
                    .'<h2>Tracking</h2>'
                    .'<p>Leg uit wanneer een trackingnummer wordt verstuurd en waar je het '
                    .'vindt.</p>',
            ],
            'returns' => [
                'title' => 'Retourneren',
                'excerpt' => 'Hoe je een artikel terugstuurt en hoe terugbetalingen verlopen.',
                'body' => $notice
                    .'<p>Vermeld de termijn die je echt aanbiedt, en wie de retourkosten betaalt. '
                    .'Alles hieronder is een voorbeeld, niet je beleid.</p>'
                    .'<h2>Retourtermijn</h2>'
                    .'<p>Vermeld binnen hoeveel dagen na levering een retour kan worden gestart, en '
                    .'in welke staat een artikel moet terugkomen. Noteer wat niet kan worden '
                    .'teruggestuurd, en waarom.</p>'
                    .'<h2>Een retour starten</h2>'
                    .'<ul>'
                    .'<li>Stuur ons een bericht met je bestelnummer en het artikel dat je wilt '
                    .'terugsturen.</li>'
                    .'<li>We antwoorden met het retouradres en eventuele nodige documenten.</li>'
                    .'<li>Stuur het artikel terug in een verpakking die de reis overleeft.</li>'
                    .'</ul>'
                    .'<h2>Terugbetalingen</h2>'
                    .'<p>Vermeld hoe lang een terugbetaling duurt zodra het artikel is aangekomen, '
                    .'en dat ze naar de oorspronkelijke betaalmethode gaat. Terugbetalingen lopen '
                    .'via je betaalprovider, dus een deel van die wachttijd ligt bij hen.</p>'
                    .'<h2>Beschadigde of verkeerde artikelen</h2>'
                    .'<p>Leg uit wat iemand moet doen als er iets kapot aankomt of niet is wat '
                    .'werd besteld, en of je daarvoor een foto nodig hebt.</p>',
            ],
        ];
    }
}
