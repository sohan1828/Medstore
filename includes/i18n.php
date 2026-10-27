<?php
/** Language: ?lang=ne|en (remembered in a cookie). Default is Nepali. */
function lang(): string {
    static $l = null;
    if ($l) return $l;
    $c = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'ne';
    $l = in_array($c, ['ne', 'en'], true) ? $c : 'ne';
    if (isset($_GET['lang']) && !headers_sent())
        setcookie('lang', $l, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    return $l;
}

function strings(): array {
    $S = [
    'en' => [
      'title' => 'Book an ambulance',
      'notice' => 'This supports emergency services and does not replace them. If in doubt, call 102 now.',
      'call102' => 'Call 102 now',
      'who' => 'Who needs the ambulance?', 'me' => 'Me', 'other' => 'Someone else',
      'type_heart' => 'Heart attack / chest pain', 'type_stroke' => 'Stroke', 'type_choking' => 'Choking',
      'type_bleeding' => 'Severe bleeding', 'type_accident' => 'Accident', 'type_general' => 'Other emergency',
      'phone' => 'Your phone number', 'pname' => "Patient's name (optional)", 'pphone' => "Patient's phone (optional)",
      'where' => 'Where is the patient?',
      'loc_gps' => "Use this phone's location (I am with the patient)", 'loc_addr' => 'I will type the address',
      'addr_label' => 'Address and a landmark (area, street, temple, shop)',
      'privacy' => 'Pressing the button shares your location and phone number with nearby ambulance drivers.',
      'fare_note' => "The fare is set by the ambulance operator. You will see an estimate once you choose a hospital.",
      'book' => 'Book ambulance',
      'getting_loc' => 'Getting your location...',
      'finding' => 'Finding the nearest ambulance (try {n}, {s} s to answer)...',
      'on_way' => 'An ambulance is on the way.', 'arrived' => 'The ambulance has arrived.',
      'driver' => 'Driver: {name}', 'plate' => 'Number plate {plate}', 'call_driver' => 'Call driver {phone}',
      'eta' => 'About {m} min away ({km} km)', 'eta_wait' => 'Finding the ambulance location...',
      'estimate' => 'Estimate only. Updated {s} s ago.', 'stale' => 'Location last updated {m} min ago. Call the driver.',
      'open_maps' => 'Open ambulance position in Maps',
      'no_live' => 'This responder has no live tracking. They will call you.', 'help_alerted' => 'Help has been alerted.',
      'call_prov' => 'Call {name}',
      'needs_staff' => 'Our control room will call you in a moment. Please also call 102 now.',
      'fallback' => 'No driver answered yet. A person is being alerted. Call 102 right now.',
      'cancelled' => 'Request cancelled.', 'completed' => 'Trip complete. We hope you are safe.',
      'cancel_btn' => 'Cancel this request', 'cancel_confirm' => 'Cancel the ambulance request?',
      'share_btn' => 'Send tracking link to family', 'share_text' => 'Follow the ambulance here: {url}',
      'hosp_title' => 'Which hospital should the patient go to?', 'hosp_later' => 'Decide later with the driver',
      'hosp_opt' => '{name}, {km} km, about Rs {lo} to Rs {hi}', 'hosp_opt_nokm' => '{name}',
      'fare_line' => 'Estimated fare: Rs {lo} to Rs {hi}. The operator confirms the final fare.',
      'hosp_note' => 'Some hospitals may not treat every emergency. Call ahead if you can.',
      'to_hospital' => 'Going to: {name}',
      'loc_blocked' => 'Location is blocked. Type the address below and press the button again, or call 102.',
      'need_phone' => 'Please enter your phone number', 'need_addr' => 'Please type the address',
      'conn_error' => 'Connection problem. Call 102 now.', 'srv_error' => 'Could not reach the server. Call 102 now.',
      'you' => 'You', 'amb' => 'Ambulance', 'map_label' => 'Map showing you and the ambulance',
      'fa_what' => '{title}: what to do now',
      'follow_title' => 'Ambulance tracking', 'follow_note' => 'You are following this ambulance for a family member or friend. This page updates by itself.',
      'other_lang' => 'नेपाली', 'other_code' => 'ne',
    ],
    'ne' => [
      'title' => 'एम्बुलेन्स बुक गर्नुहोस्',
      'notice' => 'यो सेवाले आपतकालीन सेवालाई सहयोग गर्छ, प्रतिस्थापन गर्दैन। शंका लागेमा तुरुन्तै १०२ मा फोन गर्नुहोस्।',
      'call102' => 'अहिले नै १०२ मा फोन गर्नुहोस्',
      'who' => 'एम्बुलेन्स कसलाई चाहिएको हो?', 'me' => 'मलाई', 'other' => 'अर्को व्यक्तिलाई',
      'type_heart' => 'मुटुको दौरा / छातीमा दुखाइ', 'type_stroke' => 'स्ट्रोक (पक्षाघात)', 'type_choking' => 'सास अड्किएको',
      'type_bleeding' => 'धेरै रगत बगेको', 'type_accident' => 'दुर्घटना', 'type_general' => 'अन्य आपतकालीन अवस्था',
      'phone' => 'तपाईंको फोन नम्बर', 'pname' => 'बिरामीको नाम (वैकल्पिक)', 'pphone' => 'बिरामीको फोन नम्बर (वैकल्पिक)',
      'where' => 'बिरामी कहाँ हुनुहुन्छ?',
      'loc_gps' => 'यो फोनको लोकेसन प्रयोग गर्नुहोस् (म बिरामीसँगै छु)', 'loc_addr' => 'म ठेगाना लेख्छु',
      'addr_label' => 'ठेगाना र नजिकको चिनिने ठाउँ (टोल, बाटो, मन्दिर, पसल)',
      'privacy' => 'बटन थिच्दा तपाईंको लोकेसन र फोन नम्बर नजिकका एम्बुलेन्स चालकसँग साझा हुन्छ।',
      'fare_note' => 'भाडा एम्बुलेन्स सञ्चालकले तय गर्छ। अस्पताल छानेपछि अनुमानित भाडा देखिन्छ।',
      'book' => 'एम्बुलेन्स बुक गर्नुहोस्',
      'getting_loc' => 'तपाईंको लोकेसन लिँदैछ...',
      'finding' => 'नजिकको एम्बुलेन्स खोज्दै (प्रयास {n}, जवाफ दिन {s} सेकेन्ड)...',
      'on_way' => 'एम्बुलेन्स आउँदैछ।', 'arrived' => 'एम्बुलेन्स पुगिसक्यो।',
      'driver' => 'चालक: {name}', 'plate' => 'नम्बर प्लेट {plate}', 'call_driver' => 'चालकलाई फोन गर्नुहोस् {phone}',
      'eta' => 'करिब {m} मिनेटमा पुग्छ ({km} कि.मि.)', 'eta_wait' => 'एम्बुलेन्सको स्थान खोज्दै...',
      'estimate' => 'अनुमान मात्र। {s} सेकेन्ड अघि अपडेट भयो।', 'stale' => 'स्थान {m} मिनेट अघि अपडेट भएको हो। चालकलाई फोन गर्नुहोस्।',
      'open_maps' => 'एम्बुलेन्सको स्थान नक्सामा हेर्नुहोस्',
      'no_live' => 'यो उद्धारकर्ताको प्रत्यक्ष ट्र्याकिङ छैन। उहाँहरूले तपाईंलाई फोन गर्नुहुनेछ।', 'help_alerted' => 'सहयोगका लागि खबर गरिएको छ।',
      'call_prov' => '{name} लाई फोन गर्नुहोस्',
      'needs_staff' => 'हाम्रो कन्ट्रोल रुमले छिट्टै तपाईंलाई फोन गर्नेछ। साथै अहिले नै १०२ मा पनि फोन गर्नुहोस्।',
      'fallback' => 'अहिलेसम्म कुनै चालकले जवाफ दिएका छैनन्। एक जना व्यक्तिलाई खबर गरिँदैछ। अहिले नै १०२ मा फोन गर्नुहोस्।',
      'cancelled' => 'अनुरोध रद्द गरियो।', 'completed' => 'यात्रा सम्पन्न भयो। तपाईं सुरक्षित हुनुहुन्छ भन्ने आशा गर्छौं।',
      'cancel_btn' => 'यो अनुरोध रद्द गर्नुहोस्', 'cancel_confirm' => 'एम्बुलेन्सको अनुरोध रद्द गर्ने?',
      'share_btn' => 'परिवारलाई ट्र्याकिङ लिंक पठाउनुहोस्', 'share_text' => 'एम्बुलेन्स कहाँ पुग्यो यहाँ हेर्नुहोस्: {url}',
      'hosp_title' => 'बिरामीलाई कुन अस्पताल लैजाने?', 'hosp_later' => 'पछि चालकसँग छलफल गर्छु',
      'hosp_opt' => '{name}, {km} कि.मि., करिब रु {lo} देखि रु {hi}', 'hosp_opt_nokm' => '{name}',
      'fare_line' => 'अनुमानित भाडा: रु {lo} देखि रु {hi}। अन्तिम भाडा सञ्चालकले पक्का गर्छन्।',
      'hosp_note' => 'केही अस्पतालले सबै प्रकारका आपतकालीन उपचार नगर्न सक्छन्। सकेसम्म पहिले फोन गरेर सोध्नुहोस्।',
      'to_hospital' => 'लैजाने अस्पताल: {name}',
      'loc_blocked' => 'लोकेसन बन्द छ। तल ठेगाना लेखेर फेरि बटन थिच्नुहोस्, वा १०२ मा फोन गर्नुहोस्।',
      'need_phone' => 'कृपया फोन नम्बर लेख्नुहोस्', 'need_addr' => 'कृपया ठेगाना लेख्नुहोस्',
      'conn_error' => 'इन्टरनेटमा समस्या। अहिले नै १०२ मा फोन गर्नुहोस्।', 'srv_error' => 'सर्भरसँग जोडिएन। अहिले नै १०२ मा फोन गर्नुहोस्।',
      'you' => 'तपाईं', 'amb' => 'एम्बुलेन्स', 'map_label' => 'तपाईं र एम्बुलेन्स देखाउने नक्सा',
      'fa_what' => '{title}: अहिले के गर्ने',
      'follow_title' => 'एम्बुलेन्स ट्र्याकिङ', 'follow_note' => 'तपाईं परिवारको सदस्य वा साथीका लागि यो एम्बुलेन्स हेर्दै हुनुहुन्छ। यो पृष्ठ आफैं अपडेट हुन्छ।',
      'other_lang' => 'English', 'other_code' => 'en',
    ]];
    return $S[lang()];
}
