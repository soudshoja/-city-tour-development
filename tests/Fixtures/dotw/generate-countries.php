<?php

/**
 * Generator script for the 219-country fixture.
 * Run once: php tests/fixtures/dotw/generate-countries.php
 * Output written to tests/fixtures/dotw/getallcountries-response.xml
 */

$names = [
    'ALBANIA', 'ALGERIA', 'ANDORRA', 'ANGOLA', 'ANTIGUA AND BARBUDA',
    'ARGENTINA', 'ARMENIA', 'AUSTRALIA', 'AUSTRIA', 'AZERBAIJAN',
    'BAHAMAS', 'BAHRAIN', 'BANGLADESH', 'BARBADOS', 'BELARUS',
    'BELGIUM', 'BELIZE', 'BENIN', 'BHUTAN', 'BOLIVIA',
    'BOSNIA AND HERZEGOVINA', 'BOTSWANA', 'BRAZIL', 'BRUNEI', 'BULGARIA',
    'BURKINA FASO', 'BURUNDI', 'CAMBODIA', 'CAMEROON', 'CANADA',
    'CAPE VERDE', 'CENTRAL AFRICAN REPUBLIC', 'CHAD', 'CHILE', 'CHINA',
    'COLOMBIA', 'COMOROS', 'CONGO', 'COSTA RICA', 'CROATIA',
    'CUBA', 'CYPRUS', 'CZECH REPUBLIC', 'DENMARK', 'DJIBOUTI',
    'DOMINICA', 'DOMINICAN REPUBLIC', 'EAST TIMOR', 'ECUADOR', 'EGYPT',
    'EL SALVADOR', 'EQUATORIAL GUINEA', 'ERITREA', 'ESTONIA', 'ETHIOPIA',
    'FIJI', 'FINLAND', 'FRANCE', 'GABON', 'GAMBIA',
    'GEORGIA', 'GERMANY', 'GHANA', 'GREECE', 'GRENADA',
    'GUATEMALA', 'GUINEA', 'GUINEA-BISSAU', 'GUYANA', 'HAITI',
    'HONDURAS', 'HUNGARY', 'ICELAND', 'INDIA', 'INDONESIA',
    'IRAN', 'IRAQ', 'IRELAND', 'ISRAEL', 'ITALY',
    'IVORY COAST', 'JAMAICA', 'JAPAN', 'JORDAN', 'KAZAKHSTAN',
    'KENYA', 'KIRIBATI', 'NORTH KOREA', 'SOUTH KOREA', 'KUWAIT',
    'KYRGYZSTAN', 'LAOS', 'LATVIA', 'LEBANON', 'LESOTHO',
    'LIBERIA', 'LIBYA', 'LIECHTENSTEIN', 'LITHUANIA', 'LUXEMBOURG',
    'MADAGASCAR', 'MALAWI', 'MALAYSIA', 'MALDIVES', 'MALI',
    'MALTA', 'MARSHALL ISLANDS', 'MAURITANIA', 'MAURITIUS', 'MEXICO',
    'MICRONESIA', 'MOLDOVA', 'MONACO', 'MONGOLIA', 'MONTENEGRO',
    'MOROCCO', 'MOZAMBIQUE', 'MYANMAR', 'NAMIBIA', 'NAURU',
    'NEPAL', 'NETHERLANDS', 'NEW ZEALAND', 'NICARAGUA', 'NIGER',
    'NIGERIA', 'NORTH MACEDONIA', 'NORWAY', 'OMAN', 'PAKISTAN',
    'PALAU', 'PANAMA', 'PAPUA NEW GUINEA', 'PARAGUAY', 'PERU',
    'PHILIPPINES', 'POLAND', 'PORTUGAL', 'QATAR', 'ROMANIA',
    'RUSSIA', 'RWANDA', 'SAINT LUCIA', 'SAMOA',
    'SAN MARINO', 'SAO TOME AND PRINCIPE', 'SAUDI ARABIA', 'SENEGAL', 'SERBIA',
    'SEYCHELLES', 'SIERRA LEONE', 'SINGAPORE', 'SLOVAKIA', 'SLOVENIA',
    'SOLOMON ISLANDS', 'SOMALIA', 'SOUTH AFRICA', 'SOUTH SUDAN', 'SPAIN',
    'SRI LANKA', 'SUDAN', 'SURINAME', 'SWAZILAND', 'SWEDEN',
    'SWITZERLAND', 'SYRIA', 'TAIWAN', 'TAJIKISTAN', 'TANZANIA',
    'THAILAND', 'TOGO', 'TONGA', 'TRINIDAD AND TOBAGO', 'TUNISIA',
    'TURKEY', 'TURKMENISTAN', 'TUVALU', 'UGANDA', 'UKRAINE',
    'UNITED ARAB EMIRATES', 'UNITED KINGDOM', 'UNITED STATES', 'URUGUAY', 'UZBEKISTAN',
    'VANUATU', 'VENEZUELA', 'VIETNAM', 'YEMEN', 'ZAMBIA',
    'ZIMBABWE', 'DEMOCRATIC REPUBLIC OF CONGO', 'KOSOVO', 'TIMOR-LESTE', 'ABKHAZIA',
    'SOUTH OSSETIA', 'NAGORNO-KARABAKH', 'WESTERN SAHARA', 'PALESTINE', 'COOK ISLANDS',
    'NIUE', 'TOKELAU', 'FAROE ISLANDS', 'GREENLAND', 'BERMUDA',
    'CAYMAN ISLANDS', 'JERSEY', 'GUERNSEY', 'ISLE OF MAN', 'GIBRALTAR',
    'CURACAO', 'SINT MAARTEN', 'BONAIRE', 'ARUBA', 'PUERTO RICO',
    'GUAM', 'AMERICAN SAMOA', 'NORTHERN MARIANA ISLANDS', 'TURKS AND CAICOS', 'BRITISH VIRGIN ISLANDS',
];

assert(count($names) === 219, 'Must be exactly 219 country names');

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<result>' . "\n";
$xml .= '  <successful>TRUE</successful>' . "\n";
$xml .= '  <countries count="219">' . "\n";

foreach ($names as $i => $name) {
    $code = (string) ($i + 1);
    // Override with known real DOTW codes for UAE and Kuwait so fixture is realistic
    if ($name === 'UNITED ARAB EMIRATES') {
        $code = '88';
    } elseif ($name === 'KUWAIT') {
        $code = '66';
    }
    $xml .= "    <country runno=\"{$i}\"><name>{$name}</name><code>{$code}</code></country>\n";
}

$xml .= '  </countries>' . "\n";
$xml .= '</result>' . "\n";

$outputPath = __DIR__ . '/getallcountries-response.xml';
file_put_contents($outputPath, $xml);
echo "Written: {$outputPath}\n";
echo 'Country count: ' . count($names) . "\n";
