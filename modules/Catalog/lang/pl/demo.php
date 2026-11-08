<?php

declare(strict_types=1);

return [
    'categories' => [
        'ceramics' => [
            'name' => 'Ceramika',
            'description' => 'Ręcznie wykańczane naczynia do codziennych rytuałów.',
        ],
        'kitchen_textiles' => [
            'name' => 'Tekstylia kuchenne',
            'description' => 'Naturalny len i bawełna do gotowania oraz serwowania.',
        ],
        'wood' => [
            'name' => 'Drewno',
            'description' => 'Funkcjonalne akcesoria z litego, olejowanego drewna.',
        ],
        'lighting' => [
            'name' => 'Oświetlenie',
            'description' => 'Kameralne światło do pracy i odpoczynku.',
        ],
        'stationery' => [
            'name' => 'Papiernicze',
            'description' => 'Notesy, planery i szkicowniki do dobrych pomysłów.',
        ],
        'scents' => [
            'name' => 'Zapachy',
            'description' => 'Świece i domowe aromaty o spokojnych kompozycjach.',
        ],
        'kitchen' => [
            'name' => 'Kuchnia',
            'description' => 'Codzienne akcesoria do gotowania i wspólnych posiłków.',
        ],
        'office' => [
            'name' => 'Biuro',
            'description' => 'Przemyślane dodatki do wygodnej pracy.',
        ],
        'home' => [
            'name' => 'Dom i wnętrze',
            'description' => 'Przedmioty budujące spokojne, funkcjonalne wnętrze.',
        ],
    ],
    'brands' => [
        'nord_atelier' => [
            'name' => 'Nord Atelier',
            'description' => 'Proste formy i naturalne materiały.',
        ],
        'forma_studio' => [
            'name' => 'Forma Studio',
            'description' => 'Funkcjonalne przedmioty do małych przestrzeni.',
        ],
        'mori_works' => [
            'name' => 'Mori Works',
            'description' => 'Rzemiosło inspirowane drewnem i spokojem natury.',
        ],
        'paper_grid' => [
            'name' => 'Paper & Grid',
            'description' => 'Papiernicze narzędzia do planowania i tworzenia.',
        ],
        'lumen_house' => [
            'name' => 'Lumen House',
            'description' => 'Niewielkie lampy dające dobre, ciepłe światło.',
        ],
        'scent_room' => [
            'name' => 'Scent Room',
            'description' => 'Czyste kompozycje zapachowe na co dzień.',
        ],
    ],
    'options' => [
        'capacity' => [
            'name' => 'Pojemność',
            'values' => ['250 ml', '350 ml', '450 ml'],
        ],
        'size' => [
            'name' => 'Rozmiar',
            'values' => ['S/M', 'L/XL', 'XXL'],
        ],
        'diameter' => [
            'name' => 'Średnica',
            'values' => ['28 cm', '36 cm', '44 cm'],
        ],
        'color' => [
            'name' => 'Kolor',
            'values' => ['Piaskowy', 'Grafitowy', 'Szałwiowy'],
        ],
        'format' => [
            'name' => 'Format',
            'values' => ['A6', 'A5', 'B5'],
        ],
        'scent' => [
            'name' => 'Zapach',
            'values' => ['Cedr', 'Bergamotka', 'Figa'],
        ],
    ],
    'products' => [
        'fjord' => [
            'name' => 'Kubek ceramiczny Fjord',
            'short' => 'Ręcznie szkliwiony kubek do codziennej kawy.',
            'intro' => 'Fjord powstał z myślą o spokojnych porankach i napojach, które warto pić bez pośpiechu. Gruba kamionka długo utrzymuje temperaturę, a lekko nieregularna powierzchnia przypomina o ręcznym procesie wykonania.',
            'design' => 'Każdy kubek jest formowany i szkliwiony w krótkiej serii. Reaktywne szkliwo tworzy subtelne przejścia od mlecznego błękitu do głębszego granatu, dlatego dwa egzemplarze nigdy nie wyglądają identycznie.',
            'use' => 'Wyprofilowane ucho daje pewny chwyt także wtedy, gdy kubek jest pełny. Stabilne dno sprawdza się na biurku, stole i tacy śniadaniowej, a trzy pojemności pozwalają dobrać naczynie do espresso z mlekiem, herbaty albo dużej kawy przelewowej.',
            'care' => 'Kubek można myć w zmywarce i używać w kuchence mikrofalowej. Aby zachować połysk szkliwa na lata, unikaj gwałtownego przelewania wrzątku do mocno schłodzonego naczynia.',
            'features' => [
                'kamionka wypalana w wysokiej temperaturze',
                'szkliwo bezpieczne w kontakcie z żywnością',
                'ręczne wykończenie każdego egzemplarza',
            ],
            'variants' => [
                [
                    'short' => 'Kompaktowy kubek do flat white i krótszej kawy.',
                    'intro' => 'Najmniejszy Fjord ma niski, zwarty profil i szybko ogrzewa dłonie.',
                    'use' => 'Dobrze mieści podwójne espresso z mlekiem i nie zajmuje wiele miejsca przy ekspresie.',
                    'detail' => 'Mniejsza masa sprawia, że jest wygodny również dla dzieci i osób preferujących lekkie naczynia.',
                ],
                [
                    'short' => 'Uniwersalna pojemność do kawy, kakao i herbaty.',
                    'intro' => 'Środkowy wariant zachowuje najlepszy balans pomiędzy pojemnością a wygodnym chwytem.',
                    'use' => 'To najbardziej wszechstronna wersja do codziennych napojów w domu i w pracy.',
                    'detail' => 'Proporcje dobrano tak, aby kubek mieścił się pod większością domowych ekspresów automatycznych.',
                ],
                [
                    'short' => 'Duży kubek na herbatę i długą kawę.',
                    'intro' => 'Największy Fjord ma więcej miejsca na napój, dodatki i grubą warstwę mlecznej pianki.',
                    'use' => 'Sprawdzi się podczas długiej pracy, czytania oraz wieczornego naparu z ziół.',
                    'detail' => 'Poszerzone ucho ułatwia stabilne trzymanie większej porcji, również obiema dłońmi.',
                ],
            ],
        ],
        'dune' => [
            'name' => 'Czarka ceramiczna Dune',
            'short' => 'Niska czarka do herbaty i małych deserów.',
            'intro' => 'Dune łączy prostą formę naczynia herbacianego z miękką, piaskową paletą. Niski kształt pozwala obserwować kolor naparu i naturalnie zwalnia tempo picia.',
            'design' => 'Czarka jest toczona z drobnoziarnistej kamionki, a matowe szkliwo pozostawia delikatnie wyczuwalną strukturę. Łagodnie wywinięta krawędź poprawia komfort picia i podkreśla ręczne wykonanie.',
            'use' => 'Poza zieloną i białą herbatą Dune dobrze sprawdza się do espresso, oliwek, orzechów oraz niewielkich deserów. Neutralna forma pasuje zarówno do nowoczesnej zastawy, jak i ceramiki vintage.',
            'care' => 'Można myć w zmywarce, choć delikatny program najlepiej chroni matową powierzchnię. Ewentualny osad po herbacie usuń pastą z sody oczyszczonej i wody.',
            'features' => [
                'matowe szkliwo o piaskowym odcieniu',
                'wygodna, łagodnie wywinięta krawędź',
                'forma odpowiednia także do serwowania przekąsek',
            ],
        ],
        'tide' => [
            'name' => 'Dzbanek ceramiczny Tide',
            'short' => 'Prosty dzbanek na wodę, mleko lub świeże kwiaty.',
            'intro' => 'Tide jest przedmiotem codziennym, który może stać na stole również wtedy, gdy nie jest używany. Smukła sylwetka i spokojne szkliwo tworzą dyskretny akcent w kuchni lub jadalni.',
            'design' => 'Korpus odlewany jest z wytrzymałej kamionki, a dzióbek wykańczany ręcznie, aby ograniczyć spływanie kropli. Duży uchwyt równoważy ciężar pełnego naczynia i pozostawia miejsce na swobodny chwyt.',
            'use' => 'Dzbanek pomieści wodę do rodzinnego posiłku, mleko podczas śniadania albo sezonowy bukiet. Wąski otwór pomaga prowadzić strumień, natomiast szeroka podstawa stabilizuje naczynie.',
            'care' => 'Po napojach mlecznych wypłucz dzbanek od razu ciepłą wodą. Naczynie nadaje się do zmywarki, ale przed schowaniem powinno dokładnie wyschnąć także wewnątrz.',
            'features' => [
                'precyzyjnie wyprofilowany dzióbek',
                'stabilna podstawa i wygodny uchwyt',
                'dekoracyjna forma odpowiednia do kwiatów',
            ],
        ],
        'moss' => [
            'name' => 'Lniany fartuch Moss',
            'short' => 'Miękki fartuch z regulowanym paskiem i dużą kieszenią.',
            'intro' => 'Moss chroni ubranie podczas gotowania, pracy z gliną i przesadzania roślin, nie ograniczając ruchów. Prany len od początku jest miękki, a z każdym kolejnym użyciem nabiera jeszcze bardziej naturalnego charakteru.',
            'design' => 'Krzyżujące się na plecach paski rozkładają ciężar bez ucisku na szyję. Obszerna kieszeń została podzielona na dwie części, dzięki czemu telefon, ściereczka i drobne narzędzia pozostają pod ręką.',
            'use' => 'Długość fartucha osłania tułów i uda, ale rozcięcie z przodu pozwala wygodnie chodzić oraz siadać. Stonowany kolor dobrze maskuje drobne zabrudzenia i pasuje do domowej kuchni oraz profesjonalnej pracowni.',
            'care' => 'Pierze się w temperaturze 40°C z podobnymi kolorami. Suszenie na powietrzu i rezygnacja z mocnego prasowania pozwalają zachować charakterystyczną fakturę lnu.',
            'features' => ['100% zmiękczanego lnu', 'paski krzyżowane na plecach', 'duża, dwudzielna kieszeń'],
            'variants' => [
                [
                    'short' => 'Krótszy fartuch dla drobniejszej sylwetki.',
                    'intro' => 'Wariant S/M ma skrócone paski i nieco węższy panel przedni.',
                    'use' => 'Najlepiej układa się na osobach, które zwykle noszą rozmiary od S do M.',
                    'detail' => 'Kieszeń zachowuje pełny wymiar, dlatego mniejszy rozmiar nie ogranicza funkcjonalności.',
                ],
                [
                    'short' => 'Uniwersalny fartuch o swobodnym kroju.',
                    'intro' => 'Wariant L/XL zapewnia więcej miejsca w tułowiu i dłuższe paski do regulacji.',
                    'use' => 'Sprawdzi się przy warstwowym ubraniu oraz u osób noszących rozmiary L i XL.',
                    'detail' => 'Szerszy panel boczny lepiej chroni ubranie podczas intensywnej pracy w kuchni.',
                ],
                [
                    'short' => 'Najbardziej obszerny wariant z długimi paskami.',
                    'intro' => 'Wersja XXL ma powiększony obwód i długość bez zmiany proporcji kieszeni.',
                    'use' => 'Daje swobodę ruchów przy większej sylwetce i grubszej odzieży roboczej.',
                    'detail' => 'Dłuższe paski można wiązać z tyłu albo wygodnie przeprowadzić do przodu.',
                ],
            ],
        ],
        'meadow' => [
            'name' => 'Serwetki lniane Meadow',
            'short' => 'Komplet czterech miękkich serwetek z pranego lnu.',
            'intro' => 'Meadow wprowadza na stół miękkość i naturalną fakturę bez formalnego charakteru klasycznej zastawy. Komplet czterech serwetek pasuje do codziennych śniadań, kolacji z przyjaciółmi i spokojnych uroczystości.',
            'design' => 'Każdą serwetkę wycięto z pranego lnu i wykończono wąskim podwinięciem. Materiał układa się swobodnie, a subtelne różnice w splocie są naturalną cechą włókna.',
            'use' => 'Format pozwala składać serwetki klasycznie, przewiązać sznurkiem lub położyć luźno na talerzu. Len dobrze chłonie wilgoć i szybko wysycha między kolejnymi posiłkami.',
            'care' => 'Pierz w 40°C bez wybielaczy optycznych. Serwetki można prasować, ale lekkie zagniecenia są częścią ich charakteru i nie wpływają na wygodę użycia.',
            'features' => [
                'komplet czterech sztuk',
                'prany len o miękkiej fakturze',
                'trwałe podwinięcie wszystkich krawędzi',
            ],
        ],
        'weave' => [
            'name' => 'Ściereczka kuchenna Weave',
            'short' => 'Chłonna ściereczka o wyraźnym, naturalnym splocie.',
            'intro' => 'Weave jest prostym narzędziem do zadań wykonywanych w kuchni każdego dnia. Mieszanka lnu i bawełny łączy dobrą chłonność z krótkim czasem schnięcia.',
            'design' => 'Wyraźny splot zwiększa powierzchnię materiału i ułatwia wycieranie szkła bez pozostawiania smug. Wszyta pętelka pozwala odwiesić ściereczkę tam, gdzie szybko wyschnie.',
            'use' => 'Nadaje się do osuszania naczyń, przykrywania wyrastającego ciasta i ochrony pieczywa w koszyku. Po kilku praniach materiał osiąga pełną chłonność i staje się bardziej miękki.',
            'care' => 'Można prać w 60°C. Nie stosuj płynu zmiękczającego, ponieważ osadza się na włóknach i z czasem zmniejsza ich zdolność pochłaniania wody.',
            'features' => ['mieszanka lnu i bawełny', 'splot ograniczający smugi na szkle', 'praktyczna pętelka do zawieszenia'],
        ],
        'loop' => [
            'name' => 'Dębowa taca Loop',
            'short' => 'Lekka taca z litego drewna dębowego.',
            'intro' => 'Loop została zaprojektowana do przenoszenia śniadania, porządkowania stołu i eksponowania ulubionych przedmiotów. Widoczny rysunek dębu sprawia, że każda taca ma własny układ słojów.',
            'design' => 'Toczone dno połączono z lekko uniesionym rantem, który zatrzymuje drobne przedmioty bez wizualnego obciążania formy. Drewno jest szlifowane etapami i zabezpieczane olejem dopuszczonym do kontaktu z żywnością.',
            'use' => 'Mniejsze warianty mieszczą filiżankę i deser, a największa taca pozwala zaserwować pełne śniadanie. Loop sprawdzi się również jako podstawa pod świece, karafkę lub zestaw kosmetyków.',
            'care' => 'Czyść wilgotną ściereczką i wycieraj do sucha. Nie wkładaj do zmywarki ani nie pozostawiaj w wodzie; co kilka miesięcy możesz odświeżyć powierzchnię cienką warstwą oleju do drewna.',
            'features' => [
                'lite drewno dębowe',
                'podwyższony rant chroniący zawartość',
                'olejowane wykończenie do kontaktu z żywnością',
            ],
            'variants' => [
                [
                    'short' => 'Mała taca na filiżankę, deser lub biżuterię.',
                    'intro' => 'Średnica 28 cm daje kompaktową powierzchnię i pozwala łatwo znaleźć miejsce na tacy nawet na małym stoliku.',
                    'use' => 'Wybierz ją do serwowania jednej porcji albo jako uporządkowaną bazę na drobiazgi.',
                    'detail' => 'Niewielki rozmiar ułatwia przenoszenie jedną dłonią i przechowywanie w szufladzie.',
                ],
                [
                    'short' => 'Średnia taca do śniadania i codziennego serwowania.',
                    'intro' => 'Wariant 36 cm zapewnia wygodną przestrzeń na dwie filiżanki, dzbanuszek i niewielki talerz.',
                    'use' => 'To najbardziej uniwersalny rozmiar na stół, komodę oraz stolik kawowy.',
                    'detail' => 'Proporcjonalnie wyższy rant stabilizuje naczynia podczas przenoszenia między pomieszczeniami.',
                ],
                [
                    'short' => 'Duża taca do wspólnego serwowania i ekspozycji.',
                    'intro' => 'Największa Loop mieści kompletny zestaw śniadaniowy albo kompozycję dekoracyjną.',
                    'use' => 'Sprawdzi się podczas przyjmowania gości i jako stała podstawa na dużym stole.',
                    'detail' => 'Dwie dłonie zapewniają najwygodniejsze przenoszenie w pełni obciążonej tacy.',
                ],
            ],
        ],
        'grain' => [
            'name' => 'Deska dębowa Grain',
            'short' => 'Dwustronna deska do krojenia i serwowania.',
            'intro' => 'Grain wykorzystuje twardość dębu w prostej desce, którą można zostawić na blacie. Dwie użytkowe strony pozwalają oddzielić codzienne przygotowanie składników od eleganckiego serwowania.',
            'design' => 'Zaokrąglone krawędzie są wygodne w chwycie, a odpowiednia grubość ogranicza wyginanie drewna. Każda sztuka jest ręcznie szlifowana i olejowana, by podkreślić naturalny układ słojów.',
            'use' => 'Jedna strona sprawdza się do pieczywa, warzyw i ziół, druga do podawania serów albo przekąsek. Deska może też pełnić funkcję podkładki pod gorące naczynie.',
            'care' => 'Myj ręcznie bez długiego namaczania i susz pionowo. Regularne olejowanie zapobiega przesuszaniu oraz pomaga ograniczyć wchłanianie zapachów.',
            'features' => ['dwie w pełni użytkowe strony', 'twarde drewno dębowe', 'krawędzie wygodne do podnoszenia'],
        ],
        'nest' => [
            'name' => 'Organizer biurkowy Nest',
            'short' => 'Drewniana tacka na drobiazgi używane każdego dnia.',
            'intro' => 'Nest porządkuje przedmioty, które zwykle wędrują po całym biurku. Płaska forma wyznacza im stałe miejsce bez tworzenia kolejnego dużego pojemnika.',
            'design' => 'Organizer frezowany jest z jednego kawałka drewna, dzięki czemu nie ma widocznych łączeń. Trzy łagodne zagłębienia mieszczą akcesoria o różnej wielkości i ułatwiają ich szybkie odkładanie.',
            'use' => 'Może przechowywać okulary, klucze, słuchawki, spinacze oraz pióro. Po pracy łatwo przenieść cały zestaw, odzyskując czystą powierzchnię stołu.',
            'care' => 'Kurz usuwaj miękką, suchą ściereczką. Mokre przedmioty odkładaj dopiero po osuszeniu, a powierzchnię raz na kilka miesięcy odśwież olejem do drewna.',
            'features' => ['wykonanie z jednego kawałka drewna', 'trzy funkcjonalne zagłębienia', 'filcowe podkładki chroniące blat'],
        ],
        'arc' => [
            'name' => 'Lampka biurkowa Arc',
            'short' => 'Kompaktowa lampka z regulowanym ramieniem.',
            'intro' => 'Arc kieruje światło dokładnie na książkę, klawiaturę albo szkicownik, pozostawiając resztę pomieszczenia spokojnie przyciemnioną. Smukła konstrukcja nie zabiera miejsca potrzebnego do pracy.',
            'design' => 'Metalowe ramię obraca się w dwóch punktach, a klosz można pochylić bez użycia narzędzi. Ciężka podstawa stabilizuje lampę, natomiast matowe wykończenie ogranicza refleksy.',
            'use' => 'Ciepła barwa światła sprzyja wieczornemu czytaniu, a skupiona wiązka pomaga w precyzyjnych zadaniach. Wymienne źródło światła pozwala dobrać moc do wielkości stanowiska.',
            'care' => 'Przed czyszczeniem odłącz lampę od zasilania. Metalowe elementy przecieraj suchą ściereczką, a do oprawy stosuj wyłącznie żarówki o parametrach wskazanych na etykiecie.',
            'features' => ['regulowane ramię i klosz', 'stabilna, obciążona podstawa', 'wymienne źródło światła LED'],
            'variants' => [
                [
                    'short' => 'Jasna lampka o ciepłym, neutralnym wykończeniu.',
                    'intro' => 'Piaskowy lakier zmiękcza techniczną formę Arc i dobrze łączy się z jasnym drewnem.',
                    'use' => 'Pasuje do spokojnych wnętrz, domowego biura i pokoju dziecka.',
                    'detail' => 'Matowa powierzchnia ogranicza widoczność odcisków palców oraz drobnego kurzu.',
                ],
                [
                    'short' => 'Ciemna lampka podkreślająca geometryczną sylwetkę.',
                    'intro' => 'Grafitowy wariant nadaje lampce bardziej techniczny charakter i wyraźny kontur.',
                    'use' => 'Dobrze komponuje się z czarnymi monitorami, metalem i ciemnym blatem.',
                    'detail' => 'Głęboki kolor nie odbija światła ekranu, dlatego sprzyja pracy po zmroku.',
                ],
                [
                    'short' => 'Stonowana zieleń do naturalnych i kolorowych wnętrz.',
                    'intro' => 'Szałwiowe wykończenie wprowadza delikatny kolor bez dominowania stanowiska pracy.',
                    'use' => 'Łączy się z roślinami, sklejką i tekstyliami w ciepłych odcieniach.',
                    'detail' => 'Kolor pozostaje spokojny zarówno w świetle dziennym, jak i przy zapalonej lampie.',
                ],
            ],
        ],
        'halo' => [
            'name' => 'Kinkiet ścienny Halo',
            'short' => 'Dyskretne światło do czytania i wieczornego odpoczynku.',
            'intro' => 'Halo dostarcza kierunkowe światło tam, gdzie nie ma miejsca na lampę stojącą. Niewielka forma pozostaje dyskretna w dzień i buduje kameralną atmosferę wieczorem.',
            'design' => 'Okrągła rozeta ukrywa mocowanie, a ruchomy klosz pozwala precyzyjnie skierować strumień. Wnętrze klosza rozprasza światło, ograniczając ostre kontrasty podczas czytania.',
            'use' => 'Kinkiet sprawdzi się przy łóżku, nad fotelem albo nad niewielkim blatem roboczym. Osobny włącznik ułatwia sterowanie bez uruchamiania głównego oświetlenia.',
            'care' => 'Czyść po odłączeniu zasilania, używając miękkiej suchej ściereczki. Montaż powinien wykonać elektryk zgodnie z lokalnymi zasadami instalacji.',
            'features' => [
                'ruchomy klosz do światła kierunkowego',
                'kompaktowa rozeta montażowa',
                'włącznik dostępny bezpośrednio przy oprawie',
            ],
        ],
        'ember_lamp' => [
            'name' => 'Lampka przenośna Ember',
            'short' => 'Bezprzewodowa lampka stołowa z łagodnym światłem.',
            'intro' => 'Ember daje ciepłe światło bez przewodu biegnącego przez stół. Można przenieść ją z sypialni na balkon albo postawić pośrodku stołu podczas kolacji.',
            'design' => 'Mleczny dyfuzor równomiernie rozprasza światło, a metalowa podstawa obniża środek ciężkości. Dotykowy przycisk pozwala płynnie przełączać trzy poziomy jasności.',
            'use' => 'Na najniższym poziomie lampka działa jako światło nocne, na najwyższym oświetla posiłek lub książkę. Akumulator wystarcza na kilka wieczorów zależnie od wybranej mocy.',
            'care' => 'Ładuj dołączonym przewodem USB i nie pozostawiaj urządzenia na zewnątrz podczas deszczu. Obudowę czyść lekko zwilżoną ściereczką bez agresywnych detergentów.',
            'features' => ['trzy poziomy jasności', 'ładowanie przez USB', 'stabilna podstawa i mleczny dyfuzor'],
        ],
        'grid' => [
            'name' => 'Notes w kropki Grid',
            'short' => 'Notes w twardej oprawie z papierem 100 g.',
            'intro' => 'Grid daje swobodę pustej kartki, ale delikatna siatka kropek pomaga utrzymać porządek. Nadaje się do notatek, planowania, prostych diagramów i codziennego szkicowania.',
            'design' => 'Oprawa otwiera się płasko, a szyty grzbiet pozostaje trwały przy częstym użyciu. Papier 100 g ogranicza przebijanie atramentu, a numerowane strony ułatwiają prowadzenie indeksu.',
            'use' => 'Dwie zakładki pozwalają oznaczyć bieżący tydzień i stronę z projektem. Tylna kieszeń mieści luźne kartki, natomiast elastyczna gumka zabezpiecza notes w torbie.',
            'care' => 'Przechowuj notes z dala od wilgoci i nie przeciążaj tylnej kieszeni. Do pisania markerami alkoholowymi użyj podkładki, ponieważ są bardziej wymagające niż pióro lub cienkopis.',
            'features' => ['160 numerowanych stron w kropki', 'papier 100 g przyjazny piórom', 'dwie zakładki, gumka i tylna kieszeń'],
            'variants' => [
                [
                    'short' => 'Kieszonkowy notes do szybkich zapisów w drodze.',
                    'intro' => 'Format A6 mieści się w małej torbie i pozwala mieć system notatek zawsze przy sobie.',
                    'use' => 'Najlepiej sprawdzi się jako dziennik pomysłów, lista zadań lub notatnik podróżny.',
                    'detail' => 'Mniejsza strona sprzyja krótkim wpisom i prostym układom bez zbędnego planowania.',
                ],
                [
                    'short' => 'Najbardziej uniwersalny format do pracy i planowania.',
                    'intro' => 'A5 daje wygodną powierzchnię do pisania, pozostając poręcznym w codziennym noszeniu.',
                    'use' => 'Pomieści rozbudowane notatki ze spotkań, tygodniowe rozkładówki i szkice.',
                    'detail' => 'To dobry wybór na pierwszy notes w kropki oraz jeden zeszyt do wielu zastosowań.',
                ],
                [
                    'short' => 'Duży notes do projektów, nauki i rozbudowanych diagramów.',
                    'intro' => 'Format B5 oferuje szeroką stronę, na której łatwo połączyć tekst, tabele i rysunki.',
                    'use' => 'Sprawdzi się na biurku jako główny zeszyt projektowy lub notatnik do nauki.',
                    'detail' => 'Większa powierzchnia pozwala zachować czytelne marginesy i rozbudowaną hierarchię informacji.',
                ],
            ],
        ],
        'focus' => [
            'name' => 'Planer tygodniowy Focus',
            'short' => 'Niedatowany planer pomagający zobaczyć cały tydzień.',
            'intro' => 'Focus pokazuje cały tydzień na jednej rozkładówce, bez narzucania konkretnego systemu produktywności. Brak dat pozwala zacząć w dowolnym momencie i pominąć tydzień bez marnowania stron.',
            'design' => 'Każdy dzień ma miejsce na zadania i spotkania, a osobna kolumna zbiera trzy najważniejsze priorytety. Dolna sekcja służy na notatki, pomysły i krótkie podsumowanie.',
            'use' => 'Planer pomaga rozłożyć pracę, naukę i obowiązki domowe bez przeładowania pojedynczego dnia. Czytelny układ sprawdzi się także przy planowaniu treści lub małego projektu.',
            'care' => 'Używaj ołówka, długopisu lub cienkopisu. Przechowuj planer na płasko i unikaj kontaktu z wilgocią, aby narożniki oraz klejony grzbiet zachowały formę.',
            'features' => [
                'niedatowane rozkładówki tygodniowe',
                'miejsce na trzy priorytety',
                'osobna przestrzeń na notatki i podsumowanie',
            ],
        ],
        'plain' => [
            'name' => 'Szkicownik Plain',
            'short' => 'Gładki papier do ołówka, tuszu i codziennych szkiców.',
            'intro' => 'Plain jest szkicownikiem do regularnej praktyki, szybkich obserwacji i rozwijania pomysłów bez presji gotowego dzieła. Gładkie strony pozostawiają pełną swobodę kompozycji.',
            'design' => 'Szyta oprawa otwiera się płasko, dlatego można swobodnie rysować przez środek rozkładówki. Lekko kremowy papier ogranicza zmęczenie wzroku i dobrze pokazuje zarówno grafit, jak i czarny tusz.',
            'use' => 'Papier nadaje się do ołówków, kredek, cienkopisów i lekkich lawowań. Szkicownik może służyć jako dziennik wizualny, zbiór studiów albo miejsce na projektowanie.',
            'care' => 'Przy mokrych technikach wkładaj ochronną kartkę pod aktualną stronę i pozostaw pracę do pełnego wyschnięcia. Zamykaj szkicownik gumką podczas transportu.',
            'features' => [
                'gładki, lekko kremowy papier',
                'oprawa otwierana na płasko',
                'format odpowiedni do pracy na całej rozkładówce',
            ],
        ],
        'calm' => [
            'name' => 'Świeca sojowa Calm',
            'short' => 'Naturalna świeca w bursztynowym szkle.',
            'intro' => 'Calm tworzy spokojne tło zapachowe zamiast dominować całe pomieszczenie. Roślinny wosk i bawełniany knot spalają się równomiernie, uwalniając kompozycję stopniowo.',
            'design' => 'Bursztynowe szkło chroni wosk przed światłem i daje ciepły refleks po zapaleniu. Prosta etykieta łatwo odchodzi, dzięki czemu po wypaleniu słoik można wykorzystać ponownie.',
            'use' => 'Pierwsze palenie powinno trwać do roztopienia całej powierzchni wosku. Kolejne sesje po dwie–trzy godziny pomagają wykorzystać świecę równomiernie i ograniczają tunelowanie.',
            'care' => 'Przed każdym zapaleniem skróć knot do około 5 mm. Nie zostawiaj płomienia bez nadzoru, trzymaj świecę z dala od przeciągów i zakończ palenie, gdy na dnie pozostanie około centymetra wosku.',
            'features' => ['roślinny wosk sojowy', 'bawełniany knot bez metalowego rdzenia', 'około 45 godzin równomiernego palenia'],
            'variants' => [
                [
                    'short' => 'Sucha, drzewna kompozycja o spokojnym charakterze.',
                    'intro' => 'Cedr łączy ciepłe drewno z delikatną nutą żywicy i czystego powietrza.',
                    'use' => 'Najlepiej pasuje do gabinetu, biblioteki i wieczornego odpoczynku.',
                    'detail' => 'Zapach rozwija się powoli i pozostaje blisko świecy, nie przytłaczając mniejszego wnętrza.',
                ],
                [
                    'short' => 'Świeży, cytrusowy zapach z subtelną goryczką.',
                    'intro' => 'Bergamotka wnosi jasną nutę skórki cytrusowej przełamaną zielonym akcentem.',
                    'use' => 'Dobrze sprawdzi się rano, w kuchni oraz podczas pracy wymagającej skupienia.',
                    'detail' => 'Kompozycja jest lekka i czysta, dlatego można palić ją także w cieplejszych miesiącach.',
                ],
                [
                    'short' => 'Miękka kompozycja dojrzałej figi i zielonych liści.',
                    'intro' => 'Figa łączy owocową słodycz z chłodniejszą, liściastą nutą.',
                    'use' => 'Buduje przytulną atmosferę w salonie, sypialni i podczas spokojnej kolacji.',
                    'detail' => 'Zapach pozostaje wyraźny, ale zielone tło chroni go przed nadmierną ciężkością.',
                ],
            ],
        ],
        'dawn' => [
            'name' => 'Świeca sojowa Dawn',
            'short' => 'Lekka kompozycja zielonej herbaty i cytrusów.',
            'intro' => 'Dawn pachnie jak otwarte okno o poranku: świeżo, lekko i czysto. Zielona herbata łagodzi cytrusową energię, dzięki czemu kompozycja nie staje się ostra.',
            'design' => 'Wosk sojowy wlano do przezroczystego szkła, które pokazuje kremową strukturę świecy. Minimalistyczna etykieta i jasna pokrywka pasują do lekkich, naturalnych wnętrz.',
            'use' => 'Zapach sprawdzi się podczas pracy, porządkowania domu albo spokojnego śniadania. Jest wystarczająco delikatny do małych pomieszczeń i nie konkuruje z aromatem kawy.',
            'care' => 'Przy pierwszym użyciu pozwól woskowi roztopić się do ścianek. Regularnie skracaj knot i ustawiaj świecę na odpornej na ciepło, stabilnej powierzchni.',
            'features' => [
                'zielona herbata i świeże cytrusy',
                'naturalny wosk sojowy',
                'jasne szkło nadające się do ponownego użycia',
            ],
        ],
        'ember_candle' => [
            'name' => 'Świeca sojowa Ember',
            'short' => 'Ciepła kompozycja drewna, figi i odrobiny pieprzu.',
            'intro' => 'Ember została skomponowana na długie wieczory, kiedy światło i zapach mają stworzyć poczucie ciepła. Drewno stanowi spokojną bazę dla figi oraz odrobiny czarnego pieprzu.',
            'design' => 'Ciemne szkło wzmacnia blask płomienia i chroni kompozycję zapachową przed światłem. Drewniana pokrywka służy jako podstawka podczas palenia i zamyka świecę po wystudzeniu.',
            'use' => 'Najpełniej rozwija się w salonie lub sypialni po kilkudziesięciu minutach palenia. Głębszy charakter pasuje do chłodnych miesięcy, wieczornego czytania i spotkań przy stole.',
            'care' => 'Nie pal dłużej niż cztery godziny jednorazowo. Skrócony knot ogranicza kopcenie, a zamknięcie całkowicie wystudzonej świecy pomaga zachować zapach między użyciami.',
            'features' => [
                'drewno, dojrzała figa i czarny pieprz',
                'ciemne szkło chroniące wosk',
                'drewniana pokrywka pełniąca funkcję podstawki',
            ],
        ],
    ],
    'templates' => [
        'sections' => [
            'design' => 'Projekt i wykonanie',
            'everyday' => 'Na co dzień',
            'features' => 'Najważniejsze cechy',
            'care' => 'Pielęgnacja i trwałość',
        ],
        'variant' => [
            'heading' => 'Wariant :label',
            'best_use' => 'Najlepsze zastosowanie',
            'good_to_know' => 'Warto wiedzieć',
        ],
        'standard_variant' => [
            'label' => 'Wariant standardowy',
            'short' => ':short Wersja standardowa.',
            'intro' => 'Wariant standardowy produktu :name zachowuje wszystkie materiały, proporcje i detale opisane dla całej kolekcji.',
            'use' => ':short To uniwersalny wybór do codziennego użytkowania.',
            'detail' => 'Wariant :name jest gotowy do użycia po rozpakowaniu. Szczegółowe wskazówki pielęgnacyjne znajdują się w pełnym opisie produktu.',
        ],
        'slug_fallbacks' => [
            'category' => 'kategoria',
            'brand' => 'marka',
        ],
    ],
];
