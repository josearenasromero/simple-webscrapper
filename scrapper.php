<?php

    $base_url = 'https://www.buyatoyota.com';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $base_url . '/greaterny/offers/?filters=lease&limit=100',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification
    ]);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');


    $response = curl_exec($ch);
    curl_close($ch);
    
    //I will be assuming some class names are auto-generated, specially the ones like V2gVmelU or zB1xqpab
    preg_match_all('/<footer[^>]*class="[^"]*\boffer-card-footer\b[^"]*"[^>]*>(.*?)<\/footer>/s', $response, $footer_matches);
    $footer_matches = $footer_matches[1]  ?? null;
    $cars = [];

    foreach ($footer_matches as $footer) {
        //Get only the href value
        preg_match('/<a\s+[^>]*href="([^"]+)"/', $footer, $link_matches);
        $current = $link_matches[1] ?? null;

        //Get the current single page (sp) and start extracting data
        $ch_sp = curl_init();
        curl_setopt_array($ch_sp, [
            CURLOPT_URL => $base_url . $current,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_setopt($ch_sp, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');

        $response_sp = curl_exec($ch_sp);
        curl_close($ch_sp);
        
        $car_data = extract_data($response_sp);
        if($car_data) {
            $cars[] = $car_data;
        }
    }

    if(count($cars) > 0) {
        $filename = 'output_' . date('Ymd_His') . '.csv';
        $file = fopen($filename, 'w');
        fputcsv($file, array_keys($cars[0]));
        foreach ($cars as $row) {
            fputcsv($file, $row);
        }
        fclose($file);
    }

    function extract_data($response_sp) {
        $data = [
            'year' => null, //1
            'make' => null, //2
            'model' => null, //3
            'trim' => null, //4
            'msrp' => null, //5
            'monthly_payment' => null, //6
            'monthly_payment_zero' => null, //7
            'term' => null, //8
            'due_at_signing' => null, //9
            'annual_miles' => null, //10
            'acquisition_fee' => null, //11
            'residual_value' => null, //12
            'residual_perc' => null, //13
            'capitalized_cost' => null, //14
            'money_factor' => null, //15
            'interest_rate' => null, //16
            'mileage_overage' => null, //17
            'disposition_fee' => null, //18
            'end_date' => null, //19
        ];

        $data['make'] = 'Toyota'; //2

        $title_regex = '/<[^>]*\bclass="[^"]*\bcontainer\b[^"]*"[^>]*>\s*.*?<h1[^>]*>(\d{4})\s(.*?)\sLease\sOffer<\/h1>/i';
        $title_matches = null;
        preg_match($title_regex, $response_sp, $title_matches);
        
        $data['year'] = $title_matches[1] ?? null; //1
        $data['model'] = $title_matches[2] ?? null; //3

        $model_regex = preg_quote($data['year'] . ' ' . $data['model'], '/');
        $trim_regex = '/lease a new\s+'.$model_regex.'(.*?)Model/i';
        preg_match($trim_regex, $response_sp, $trim_matches);

        $acq_regex = '/Acquisition fee of \$(\d{1,3}(?:,\d{3})*),/i';
        preg_match($acq_regex, $response_sp, $acq_matches);

        $acq_value = $acq_matches[1] ?? null;
        $acq_value = str_replace('$', '', $acq_value);
        $acq_value = (float) str_replace(',', '', $acq_value);

        $data['trim'] = isset($trim_matches[1]) ? trim($trim_matches[1]) : null; //4
        $data['acquisition_fee'] = $acq_value; //11

        //Get the required content to search a bit faster
        $disclaimer_regex = '/<ul id="disclaimerContent".*?>(.*?)<\/ul>/s';
        preg_match($disclaimer_regex, $response_sp, $disclaimer_matches);
        $disclaimer_string = $disclaimer_matches[1] ?? null;

        $msrp_pattern = '/Total SRP of \$(\d{1,3}(?:,\d{3})*)/i';
        $capitalized_cost_pattern = '/net capitalized cost of \$(\d{1,3}(?:,\d{3})*)/i';
        $residual_value_pattern = '/lease end purchase amount of \$(\d{1,3}(?:,\d{3})*)/i';
        $mileage_overage_pattern = '/\$([\d.]+) per mile/i';
        $annual_miles_pattern = '/(\d{1,3}(?:,\d{3})*)(?:\s+|\s*-\s*)miles per year/i';
        $disposition_fee_pattern = '/\$([\d,]+) disposition fee/i';
        $end_date_pattern = '/Expires (\d{2}-\d{2}-\d{4})/i';

        preg_match($msrp_pattern, $disclaimer_string, $msrp_match);
        preg_match($capitalized_cost_pattern, $disclaimer_string, $capitalized_cost_match);
        preg_match($residual_value_pattern, $disclaimer_string, $residual_value_match);
        preg_match($mileage_overage_pattern, $disclaimer_string, $mileage_overage_match);
        preg_match($annual_miles_pattern, $disclaimer_string, $annual_miles_match);
        preg_match($disposition_fee_pattern, $disclaimer_string, $disposition_fee_match);
        preg_match($end_date_pattern, $disclaimer_string, $end_date_match);

        $msrp = $msrp_match[1] ?? null; //5
        $capitalized_cost = $capitalized_cost_match[1] ?? null; //14
        $capitalized_cost = str_replace('$', '', $capitalized_cost);
        $residual_value = $residual_value_match[1] ?? null; //12
        $residual_value = str_replace('$', '', $residual_value);
        $mileage_overage = $mileage_overage_match[1] ?? null; //17
        $annual_miles = $annual_miles_match[1] ?? null; //10
        $annual_miles_value = (float) str_replace(',', '', $annual_miles);
        $disposition_fee = $disposition_fee_match[1] ?? null; //18
        $disposition_fee = str_replace('$', '', $disposition_fee);
        $end_date = $end_date_match[1] ?? null; //19

        //Some additional formatting
        $msrp = (float) str_replace(',', '', $msrp);
        $capitalized_cost = (float) str_replace(',', '', $capitalized_cost);
        $residual_value = (float) str_replace(',', '', $residual_value);
        $disposition_fee = (float) str_replace(',', '', $disposition_fee);
        $end_date = date('Y-d-m', strtotime($end_date));

        $data['msrp'] = $msrp;
        $data['capitalized_cost'] = $capitalized_cost;
        $data['residual_value'] = $residual_value;
        $data['mileage_overage'] = $mileage_overage;
        $data['annual_miles'] = $annual_miles_value;
        $data['disposition_fee'] = $disposition_fee;
        $data['end_date'] = $end_date;

        $offer_regex = '/<div[^>]*class="[^"]*offer-dt-numberMain[^"]*"[^>]*>(.*?)<\/div>/s';
        $number_regex = '/\b(\d{1,3}(?:,\d{3})*)(?=\D|$)\b/';
        preg_match($offer_regex, $response_sp, $offer_matches);
        $offer_string = $offer_matches[1] ?? null;
        preg_match($number_regex, $offer_string, $number_matches);
        
        $payment_text = $number_matches[1] ?? null;

        $term_due_regex = '/<div[^>]*class="[^"]*\boffer-dt-number\b[^"]*"[^>]*>(.*?)<\/div>/s';
        preg_match_all($term_due_regex, $response_sp, $term_due_matches);

        $term_value = $term_due_matches[1][0] ?? '';
        $due_value = $term_due_matches[1][1] ?? '';
        preg_match($number_regex, $term_value, $term_matches);
        preg_match($number_regex, $due_value, $due_matches);

        $term_text = $term_matches[1] ?? null;
        $due_text = $due_matches[1] ?? null;

        $payment_value = str_replace('$', '', $payment_text);
        $term_value = str_replace('$', '', $term_text);
        $term_value = (float) str_replace(',', '', $term_value);
        $due_value = str_replace('$', '', $due_text);
        $due_value = (float) str_replace(',', '', $due_value);

        $data['monthly_payment'] = (float) str_replace(',', '', $payment_value); //6
        $data['term'] = $term_value; //8
        $data['due_at_signing'] = $due_value; //9

        //Just in case something is 0 or null, using max to avoid division by zero
        $data['monthly_payment_zero'] = round($data['monthly_payment'] + (($data['due_at_signing'] - $data['monthly_payment']) / max($data['term'], 1)), 2); //7
        $data['residual_perc'] = round(($data['residual_value'] / max($data['msrp'], 1)) * 100, 2); //13
        $data['money_factor'] = ($data['monthly_payment'] - (($data['capitalized_cost'] - $data['residual_value']) / max($data['term'], 1))) / max(($data['capitalized_cost'] + $data['residual_value']), 1); //15
        $data['interest_rate'] = round($data['money_factor'] * 2400, 2); //16

        return $data;
    }