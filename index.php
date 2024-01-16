<?php

$ini_array = parse_ini_file("keywords.ini");

$creditKeywords = explode(',', $ini_array['credit_keywords']);
$debitKeywords = explode(',', $ini_array['debit_keywords']);

$creditKeywords = array_map('trim', $creditKeywords);
$debitKeywords = array_map('trim', $debitKeywords);

date_default_timezone_set('Asia/Karachi');

if (isset($_POST['submit'])) {
    
    //@unlink('sms.lst');

    // Check if any Android device is connected
    exec("adb devices", $devicesOutput, $returnVar);

    // Check if the output contains a device identifier
    $deviceConnected = false;
    foreach ($devicesOutput as $line) {
        if (str_contains($line, "\tdevice")) {
            $deviceConnected = true;
            break;
        }
    }

    if ($deviceConnected) {
        
        $adbCommand = '.\adb.exe shell "content query --uri content://sms/ --projection date:body" > sms.lst';

        exec($adbCommand, $output, $returnVar);

        if ($returnVar == 0) {
            // nothing....
        } else {
            echo "Error: Unable to export SMS messages.\n";
        }
    } else {
        echo "Error: No Android device connected.\n";
    }
}

if (file_exists('sms.lst') && filesize('sms.lst') > 0) {

    $messages = [];
    
    $smsContent = file_get_contents('sms.lst');
    $smsMessages = explode("Row:", $smsContent);

    $smsMessages = array_filter($smsMessages, function($message) {
        return !empty($message);
    });

    foreach ($smsMessages as $message) {
        $body = explode("body=", $message)[1] ?? null;
        $date = explode("date=", $message);
        
        if (!isset($body)) {
            continue;
        }
        
        $date = explode("date=", $message);
        preg_match("/\d+, /", $date[1], $matches);
        $date = rtrim(trim($matches[0]), ',');
        
        preg_match("/PKR \d+(,\d+)*(\.\d+)?|Rs\.? \d+(,\d+)*(\.\d+)?|\d+(,\d+)*(\.\d+)? amount/i", $body, $amountMatches);
        $amount = isset($amountMatches[0]) ? $amountMatches[0] : 0;
        
        $amount = preg_replace("/[^0-9.]/", "", $amount);
        $amount = trim($amount, '.');

        $messageData = [
            'date' => $date,
            'message' => $body,
            'amount' => $amount,
            'is_credit' => (bool)preg_match("/\b(" . implode('|', $creditKeywords) . ")\b/i", $body),
            'is_debit' => (bool)preg_match("/\b(" . implode('|', $debitKeywords) . ")\b/i", $body),
        ];
        
        $messages[] = $messageData;
    }

    $messages = array_filter($messages, function($message) {
        return $message['is_credit'] || $message['is_debit'];
    });

    //var_dump($messages);exit;

    usort($messages, function($a, $b) {
        return $b['date'] - $a['date'];
    });

    // calculate totals

    $totalCreditAmount = 0;
    $totalDebitAmount = 0;

    foreach ($messages as $message) {
        if ($message['is_credit']) {
            $totalCreditAmount += (int)$message['amount'];
        } elseif ($message['is_debit']) {
            $totalDebitAmount += (int)$message['amount'];
        }
    }

    // calculate totals for current month only
    $currentMonth = date('m');
    $currentYear = date('Y');

    $totalCreditAmountCurrentMonth = 0;
    $totalDebitAmountCurrentMonth = 0;

    foreach ($messages as $message) {
        if (date('m', substr($message['date'], 0, 10)) == $currentMonth && date('Y', substr($message['date'], 0, 10)) == $currentYear) {
            if ($message['is_credit']) {
                $totalCreditAmountCurrentMonth += (int)$message['amount'];
            } elseif ($message['is_debit']) {
                $totalDebitAmountCurrentMonth += (int)$message['amount'];
            }
        }
    }
    
    // calculate totals for current year only
    $totalCreditAmountCurrentYear = 0;
    $totalDebitAmountCurrentYear = 0;

    foreach ($messages as $message) {
        if (date('Y', substr($message['date'], 0, 10)) == $currentYear) {
            if ($message['is_credit']) {
                $totalCreditAmountCurrentYear += (int)$message['amount'];
            } elseif ($message['is_debit']) {
                $totalDebitAmountCurrentYear += (int)$message['amount'];
            }
        }
    }

} else {
    echo "Error: sms.lst file does not exist or is empty.\n";
}

?>

    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
        
    <div class="container" style="margin-top:30px; margin-bottom:50px;">

        <div class="row">
            <div class="col-md-12">
                <form action="" method="post">
                    <button class="btn btn-primary mb-2" type="submit" name="submit">Refresh Data</button>
                </form>
            </div>
        </div>

        <div class="row mb-2">
            <div class="col-md-2 ml-auto">
                <select class="form-control" id="timePeriodSelect">
                    <option value="month">Month</option>
                    <option value="year">Year</option>
                    <option value="overall">Overall</option>
                </select>
            </div>
        </div>
        
        <?php if (file_exists('sms.lst') && filesize('sms.lst') > 0) : ?>

        <div class="row" id="monthData">
            <div class="col-md-6">
                <div class="alert alert-success" role="alert">
                    <h6 class="alert-heading">Credit Amount This Month</h6>
                    <strong style="font-size: 16px;"><?='PKR '. number_format($totalCreditAmountCurrentMonth)?></strong>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-danger" role="alert">
                    <h6 class="alert-heading">Debit Amount This Month</h6>
                    <strong style="font-size: 16px;"><?='PKR '. number_format($totalDebitAmountCurrentMonth)?></strong>
                </div>
            </div>
        </div>

        <div class="row" id="yearData" style="display: none;">
            <div class="col-md-6">
                <div class="alert alert-success" role="alert">
                    <h6 class="alert-heading">Credit Amount This Year</h6>
                    <strong style="font-size: 16px;"><?='PKR '. number_format($totalCreditAmountCurrentYear)?></strong>
                </div>
                </div>
            <div class="col-md-6">
                <div class="alert alert-danger" role="alert">
                    <h6 class="alert-heading">Debit Amount This Year</h6>
                    <strong style="font-size: 16px;"><?='PKR '. number_format($totalDebitAmountCurrentYear)?></strong>
                </div>
            </div>
        </div>

        <div class="row" id="allData" style="display: none;">
            <div class="col-md-6">
                <div class="alert alert-success" role="alert">
                    <h6 class="alert-heading">All Credit Amount</h6>
                    <strong style="font-size: 16px;"><?='PKR '. number_format($totalCreditAmount)?></strong>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-danger" role="alert">
                    <h6 class="alert-heading">All Debit Amount</h6>
                    <strong style="font-size: 16px;"><?='PKR '. number_format($totalDebitAmount)?></strong>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Date</th>
                            <th>Message</th>
                            <th style="width: 100px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($messages as $message) :?>
                        <tr style="background-color: <?=$message['is_credit'] ? 'lightgreen' : '#eee'?>">
                            <td align="center"><?=date('d M y', substr($message['date'], 0, 10))?></td>
                            <td><?=$message['message']?></td>
                            <td><?='PKR '. number_format((int)$message['amount'])?></td>
                        </tr>
                        <?php endforeach?>
                        
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php endif ?>

    <script>
        $(document).ready(function() {
            $('.table').DataTable({
                "order": [],
                "pageLength" :25
            });

            $('#timePeriodSelect').change(function(){
                var selectedValue = $(this).val();

                if(selectedValue == 'month'){
                    $('#monthData').show();
                    $('#yearData').hide();
                    $('#allData').hide();
                } else if(selectedValue == 'year'){
                    $('#monthData').hide();
                    $('#yearData').show();
                    $('#allData').hide();
                } else {
                    $('#monthData').hide();
                    $('#yearData').hide();
                    $('#allData').show();
                }
            });
            
        });
    </script>

