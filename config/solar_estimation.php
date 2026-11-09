<?php

return [
    'defaults' => array_merge(json_decode(file_get_contents(database_path('seeders/data/solar-workbook.json')), true)['defaults'], [
        'battery_enabled' => true, 'inverter_count' => 1, 'discount_amount' => 0,
        'notes' => '', 'terms' => 'Payment, delivery and warranty terms are subject to written agreement.',
    ]),
    'references' => [
        'Cable length, current and voltage drop' => 'https://www.victronenergy.com/media/pg/The_Wiring_Unlimited_book/en/dc-wiring.html',
        'PV string voltage and current limits' => 'https://files.sma.de/downloads/DC-PL-en-11.pdf',
        'Mounting instructions and roof layout' => 'https://catalogue.k2-systems.com/media/f3/e8/39/BasicRail-S-Dome-6-assembly-en.pdf',
        'Matching PV connector families' => 'https://www.staubli.com/us/en/electrical-connectors/industries/renewable-energy/cross-connection.html',
        'Meralco net-metering application' => 'https://www.meralco.com.ph/residential/energy-solutions/solar-and-renewables/re-net-metering-application',
    ],
];
