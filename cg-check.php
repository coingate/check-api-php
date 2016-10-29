<?php
    define('COINGATE_ISSUE_CHECKER_VERSION', '1.0.0');

    $coingateTestStatus = false;
    $coingateCredentials = array('app_id'=>'','api_key'=>'','api_secret'=>'','environment'=>'sandbox');
    $debugInfo = array();

    $internetConnectionStatus = checkConnection('google.com');
    $coingateSandboxConnectionStatus = checkConnection('sandbox.coingate.com');
    $coingateLiveConnectionStatus = checkConnection('coingate.com');

    $hostStatus = getHostStatus();

    $curlInfo = curl_version();
    $phpVersion = phpversion();

    array_push($debugInfo, array(
        'title' => 'PHP Version',
        'value' => $phpVersion
    ));

    array_push($debugInfo, array(
        'title' => 'cURL Info',
        'value' => var_export($curlInfo, true)
    ));

    array_push($debugInfo, array(
        'title' => 'Internet Connection Status',
        'value' => $internetConnectionStatus ? 'Yes' : 'No'
    ));

    array_push($debugInfo, array(
        'title' => 'CoinGate Sandbox Connection',
        'value' => $coingateSandboxConnectionStatus ? 'Yes' : 'No'
    ));

    array_push($debugInfo, array(
        'title' => 'CoinGate Live Connection',
        'value' => $coingateLiveConnectionStatus ? 'Yes' : 'No'
    ));

    array_push($debugInfo, array(
        'title' => 'Host',
        'value' => $hostStatus ? 'Yes' : 'No (IP: '.(isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'NO-DATA').'; Remote Name: '.(isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'NO-DATA').')'
    ));

    if (isset($_POST['coingate'])) {
        $coingateCredentials = $_POST['coingate'];

        if (isset($coingateCredentials['app_id']) && $coingateCredentials['app_id'] != ''
            && isset($coingateCredentials['api_key']) && $coingateCredentials['api_key'] != ''
            && isset($coingateCredentials['api_secret']) && $coingateCredentials['api_secret'] != ''
            && isset($coingateCredentials['environment']) && $coingateCredentials['environment'] != ''
        ) {
            $request = request('/auth/test', 'GET', array(), $coingateCredentials);

            if (isset($request['status']) && $request['status'] == 200) {
                $coingateTestStatus = true;
            } elseif (isset($request['status']) && $request['status'] == 0) {
                $coingateTestStatus = array(
                    'reason' => 'CURLError',
                    'message' => 'Got error from cURL: "'.$request['curlError'].'"'
                );
            } elseif (isset($request['status'])) {
                $coingateTestStatus = $request;
            } else {
                $coingateTestStatus = array('reason'=>'UnknownError','message'=>'There is unknown error occured.');
            }

            array_push($debugInfo, array(
                'title' => 'CoinGate Test Info',
                'value' => var_export($coingateTestStatus, true)
            ));
        } else {
            $coingateTestStatus = array('reason'=>'FormInvalid', 'message'=>'Please fill all fields correctly.');
        }

    }

    $dependecies = array(
        array(
            'status' => $internetConnectionStatus,
            'title' => 'Internet Connection',
            'description' => array(
                'good' => 'Internet connection is good.',
                'bad' => 'There is no internet connection. Please check your internet connection and try again.'
            )
        ),
        array(
            'status' => $coingateSandboxConnectionStatus,
            'title' => 'CoinGate Sandbox Connection',
            'description' => array(
                'good' => 'Connection with sandbox of CoinGate is good.',
                'bad' => 'Connection with sandbox of CoinGate is bad,'
            )
        ),
        array(
            'status' => $coingateLiveConnectionStatus,
            'title' => 'CoinGate Live Connection',
            'description' => array(
                'good' => 'Connection with live of CoinGate is good.',
                'bad' => 'Connection with live of CoinGate is bad.'
            )
        ),
        array(
            'status' => $hostStatus,
            'title' => 'Host',
            'description' => array(
                'good' => 'It seems your host is accessible from outside.',
                'bad' => 'You are using <a href="https://en.wikipedia.org/wiki/Private_network" target="_blank">private network</a> IP address which is not accessible from outside. It means what CoinGate callbacks will not reach you and your order status will not update.'
            )
        )
    );

    function checkConnection($url, $port=443)
    {
        $connected = @fsockopen($url, $port);

        if ($connected) {
            $is_conn = true;
            fclose($connected);
        } else {
            $is_conn = false;
        }

        return $is_conn;
    }

    function getHostStatus()
    {
        if (isset($_SERVER['SERVER_ADDR'])) {
            $serverIP = $_SERVER['SERVER_ADDR'];

            return preg_match("/(^127\.)|(^192\.168\.)|(^10\.)|(^172\.1[6-9]\.)|(^172\.2[0-9]\.)|(^172\.3[0-1]\.)|(^::1$)|(^[fF][cCdD])/", $serverIP);
        } else {
            # No address found
            return false;
        }
    }

    function printDependency($dependecyStatus, $dependecyTitle, $depedencyGoodText, $dependecyBadText)
    {
        $statusStyle = $dependecyStatus ? 'success' : 'danger';
        $statusText = $dependecyStatus ? 'GOOD' : 'BAD';
        $title = $dependecyTitle;
        $description = $dependecyStatus ? $depedencyGoodText : $dependecyBadText;

        return (
        "<li class='list-group-item'>
            <span class='tag tag-$statusStyle tag-pill pull-xs-right'>$statusText</span>
            <span>
                <span class='dependency-title'>$title</span>
                <small class='dependency-description'>$description</small>
            </span>
        </li>"
        );
    }

    function printDebugInfo($title, $value)
    {
        return (
            "<div style='padding:2px;'>
                <b>$title</b>: $value
            </div>"
        );
    }

    function request($url, $method = 'POST', $params = array(), $authentication = array())
    {
        $app_id      = $authentication['app_id'];
        $app_key     = $authentication['api_key'];
        $app_secret  = $authentication['api_secret'];
        $environment = $authentication['environment'];
        $user_agent  = 'CoinGate Issue Checker ' . COINGATE_ISSUE_CHECKER_VERSION;

        $url       = ($environment === 'sandbox' ? 'https://sandbox.coingate.com/api/v1' : 'https://coingate.com/api/v1') . $url;
        $nonce     = (int)(microtime(true) * 1e6);
        $message   = $nonce . $app_id . $app_key;
        $signature = hash_hmac('sha256', $message, $app_secret);

        $headers   = array();
        $headers[] = 'Access-Key: ' . $app_key;
        $headers[] = 'Access-Nonce: ' . $nonce;
        $headers[] = 'Access-Signature: ' . $signature;

        $curl      = curl_init();

        $curl_options = array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL            => $url
        );

        if ($method == 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            array_merge($curl_options, array(CURLOPT_POST => 1));
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt_array($curl, $curl_options);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response    = json_decode(curl_exec($curl), TRUE);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        return array('status'=>$http_status,'response'=>$response,'curlError'=>curl_error($curl));
    }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    <title>CoinGate Issue Checker v<?= COINGATE_ISSUE_CHECKER_VERSION ?></title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.3/css/bootstrap.min.css" integrity="sha384-MIwDKRSSImVFAZCVLtU0LMDdON6KVCrZHyVQQj6e8wIEJkW4tvwqXrbMIya1vriY" crossorigin="anonymous">

    <link href="https://fonts.googleapis.com/css?family=Roboto:100,400" rel="stylesheet">

    <style>
        body{font-family: 'Roboto', sans-serif;padding-bottom:15px;}
        .dependency-title{font-weight:400;}
        p,small,div{font-weight:100;}
        .dependency-description{word-wrap:break-word;display:block;}
    </style>
  </head>
  <body>
    <div class="container-fluid" style="padding-top:15px;">
        <div class="row">
            <div class="col-sm-12">
                <h1 class="text-xs-center">CoinGate Issue Checker v<?= COINGATE_ISSUE_CHECKER_VERSION ?></h1>
                <p class="text-xs-center">This file will help you to determine issues related to CoinGate.</p>
            </div>
        </div>

        <br>

        <div id="dependencies" class="row">
            <div class="col-sm-12">
                <h2 class="text-xs-left">F.A.Q.</h2>
                <p>Please be sure to check the <a href="#" target="_blank">Frequently Asked Questions</a> before asking any kind of question.</p>
            </div>
        </div>

        <hr>

        <div id="dependencies" class="row">
            <div class="col-sm-12">
                <h2 class="text-xs-left">Dependecies</h2>
                <p>We will check if your server have all needed dependencies for CoinGate service to work.</p>

                <ul class="list-group">
                    <?php
                        foreach ($dependecies as $dependecy) {
                            echo printDependency($dependecy['status'], $dependecy['title'], $dependecy['description']['good'], $dependecy['description']['bad']);
                        }
                    ?>
                </ul>
            </div>
        </div>

        <hr>

        <div id="coingate-test" class="row">
            <div class="col-sm-12">
                <h2>CoinGate Test</h2>
                <p>Test your API app. Enter your CoinGate API app credentials below and click <i>Test</i></p>

                <div class="row">
                    <div class="col-sm-12">
                        <?php if ($coingateTestStatus === true): ?>
                            <div class="alert alert-success">
                                Seems there is everything okey.
                            </div>
                        <?php elseif ($coingateTestStatus !== false): ?>
                            <div class="alert alert-danger">
                                <?= $coingateTestStatus['message'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <form action="#coingate-test" method="POST">
                  <div class="form-group row">
                    <label for="coingate_app_id" class="col-sm-2 col-form-label">App ID</label>
                    <div class="col-sm-10">
                      <input type="number" name="coingate[app_id]" value="<?= $coingateCredentials['app_id'] ?>" class="form-control" id="coingate_app_id" placeholder="App ID" required>
                    </div>
                  </div>

                  <div class="form-group row">
                    <label for="coingate_api_key" class="col-sm-2 col-form-label">API Key</label>
                    <div class="col-sm-10">
                      <input type="text" name="coingate[api_key]" value="<?= $coingateCredentials['api_key'] ?>" class="form-control" id="coingate_api_key" placeholder="API Key" required>
                    </div>
                  </div>

                  <div class="form-group row">
                    <label for="coingate_api_secret" class="col-sm-2 col-form-label">API Secret</label>
                    <div class="col-sm-10">
                      <input type="password" name="coingate[api_secret]" value="<?= $coingateCredentials['api_secret'] ?>" class="form-control" id="coingate_api_secret" placeholder="API Secret" >
                    </div>
                  </div>

                  <div class="form-group row">
                    <label for="coingate_environment" class="col-sm-2 col-form-label">Environment</label>
                    <div class="col-sm-10">
                        <select name="coingate[environment]" id="coingate_environment" class="form-control" required>
                            <option value="sandbox"<?= $coingateCredentials['environment'] == 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                            <option value="live"<?= $coingateCredentials['environment'] == 'live' ? 'selected' : '' ?>>Live</option>
                        </select>
                    </div>
                  </div>

                  <div class="form-group row">
                    <div class="offset-sm-2 col-sm-10">
                      <button type="submit" class="btn btn-primary">Test</button>
                    </div>
                  </div>
                </form>
            </div>
        </div>

        <hr>

        <div id="debug-info" class="row">
            <div class="col-sm-12">
                <h2>Debug Info</h2>
                <p>
                    <span class="text-<?= $coingateTestStatus === false ? 'danger' : 'success' ?>">Please first submit <a href="#coingate-test">CoinGate Test</a> form for more debug details.</span> Copy and send it to <a href="mailto:support@coingate.com">support@coingate.com</a>.
                </p>

                <div style="background:#f1f1f1;padding:15px;font-size:12px;">
                    <?php
                        foreach($debugInfo as $row) {
                            echo printDebugInfo($row['title'], $row['value']);
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery first, then Tether, then Bootstrap JS. -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.0.0/jquery.min.js" integrity="sha384-THPy051/pYDQGanwU6poAc/hOdQxjnOEXzbT+OuUAFqNqFjL+4IGLBgCJC3ZOShY" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.2.0/js/tether.min.js" integrity="sha384-Plbmg8JY28KFelvJVai01l8WyZzrYWG825m+cZ0eDDS1f7d/js6ikvy1+X+guPIB" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.3/js/bootstrap.min.js" integrity="sha384-ux8v3A6CPtOTqOzMKiuo3d/DomGaaClxFYdCu2HPMBEkf6x2xiDyJ7gkXU0MWwaD" crossorigin="anonymous"></script>
  </body>
</html>
