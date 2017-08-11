<?php

$con = mssql_connect("FERACK2:1433", "DWND", "download@rack2") or die(mssql_get_last_message());
mssql_select_db("Bondpricing", $con) or die(mssql_get_last_message());
mssql_query("SET ANSI_NULLS ON") or die(mssql_get_last_message());
mssql_query("SET ANSI_WARNINGS ON") or die(mssql_get_last_message());
mssql_query("SET ANSI_PADDING ON") or die(mssql_get_last_message());
mssql_query("SET CONCAT_NULL_YIELDS_NULL ON") or die(mssql_get_last_message());
mssql_query("SET QUOTED_IDENTIFIER ON") or die(mssql_get_last_message());

$sqlstatus = mssql_query("SELECT top(1) * FROM Finra_Download where (Muni_Status='0' or Muni_Status='' or Muni_Status is null) and CAST(Finra_Date as date)>='2012-10-01' and CAST(Finra_Date as date)<='2013-12-31'");

if (mssql_num_rows($sqlstatus) > 0) {
    $row = mssql_fetch_assoc($sqlstatus);
//13-July-2011

    $date = date('d-M-Y', strtotime($row['Finra_Date']));

    $d = date('d', strtotime($date));
    $m = date('m', strtotime($date));
    $y = date('Y', strtotime($date));
    echo 'mdy:'.$m . '-' . $d . '-' . $y.'<br>';
    $fromdate = $m . '%252F' . $d . '%252F' . $y;

    $end = 1;
    $start = 0;
    for ($i = 0; $i < $end; $i++) {
        $url = 'http://finra-markets.morningstar.com/bondSearch.jsp';
        //Corparate
        //$post = "count=2000&searchtype=T&query=%7B%22Keywords%22%3A%5B%7B%22Name%22%3A%22debtOrAssetClass%22%2C%22Value%22%3A%223%22%7D%2C%7B%22Name%22%3A%22showResultsAs%22%2C%22Value%22%3A%22T%22%7D%2C%7B%22Name%22%3A%22tradeDate%22%2C%22minValue%22%3A%22" . $fromdate . "%22%2C%22maxValue%22%3A%22" . $fromdate . "%22%7D%5D%7D&sortfield=tradeDate&sorttype=1&start=" . $start . "&curPage=" . ($i + 1);
        //Goverment
        //$post = "count=2000&searchtype=T&query=%7B%22Keywords%22%3A%5B%7B%22Name%22%3A%22debtOrAssetClass%22%2C%22Value%22%3A%221%2C2%22%7D%2C%7B%22Name%22%3A%22showResultsAs%22%2C%22Value%22%3A%22T%22%7D%2C%7B%22Name%22%3A%22tradeDate%22%2C%22minValue%22%3A%22" . $fromdate . "%22%2C%22maxValue%22%3A%22" . $fromdate . "%22%7D%5D%7D&sortfield=tradeDate&sorttype=2&start=" . $start . "&curPage=" . ($i + 1);
        //Muni
        $post = "count=2000&searchtype=T&query=%7B%22Keywords%22%3A%5B%7B%22Name%22%3A%22debtOrAssetClass%22%2C%22Value%22%3A%224%22%7D%2C%7B%22Name%22%3A%22showResultsAs%22%2C%22Value%22%3A%22T%22%7D%2C%7B%22Name%22%3A%22tradeDate%22%2C%22minValue%22%3A%22" . $fromdate . "%22%2C%22maxValue%22%3A%22" . $fromdate . "%22%7D%5D%7D&sortfield=tradeDate&sorttype=2&start=" . $start . "&curPage=" . ($i + 1);

        $content = http_url($url, $post);

        $DirName = '/FactEntry/FEManuals/HistorialFinraDownload/Municipal/' . date('Y', strtotime($date)) . '/' . date('dMY', strtotime($date));
        !file_exists($DirName) ? mkdir($DirName, 0777, TRUE) ? '' : die($DirName) : '';

        file_put_contents($DirName . '/' . $i . '.json', $content);

        if ($i == 0) {

            if (stripos($content, '{T:{"Columns":') === FALSE) {
                die("not found");
            }

            $data = str_ireplace('{T:{"Columns":', '{"T":{"Columns":', str_ireplace('{B:{"Columns":', '{"B":{"Columns":', $content));

            //{T:{"Columns":[],"Rows":0,"Count":0,"hasData":false,"errorMsg":null,"LatestDate":null}}

            $data2 = json_decode($data, true);
            if ($data2["T"]["Count"] == 0) {
                mssql_query("UPDATE BondPricing .dbo.Finra_Download SET Muni_Status='9' where id='" . $row['id'] . "'");
                echo "UPDATE BondPricing .dbo.Finra_Download SET Muni_Status='9' where id='" . $row['id'] . "'";
                break;
            } else {
                if (stripos($data, '{"T":{"Columns":') !== FALSE) {
                    $datacol = $data2["T"]['Columns'];
                }

                $count = explode('.', ($data2["T"]["Count"] / 2000));
                print_r($count);
                $end = $count[0] + 1;
                echo "<br>End:" . $end;
                //print_r($datacol);
            }
        }
        if (($end - 1) == $i) {
            mssql_query("UPDATE BondPricing .dbo.Finra_Download SET Muni_Status='1' where id='" . $row['id'] . "'");
            echo "UPDATE BondPricing .dbo.Finra_Download SET Muni_Status='1' where id='" . $row['id'] . "'";
        }
        $start += 2000;
    }
    echo "<head><script>setTimeout(function(){location.reload();}, 1000);</script></head>";
}

function http_url($url, $post) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Referer:http://finra-markets.morningstar.com/BondCenter/Results.jsp",
        "Origin:http://finra-markets.morningstar.com ",
        "User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36",
        "Cookie:mid=11890051699261078181; msFinraHasAgreed=true; qs_wsid=826C3492114BB91E5C23B0CF9AF85A49; __utmt=1; SessionID=826C3492114BB91E5C23B0CF9AF85A49; UsrID=41151; UsrName=FINRA.QSAPIDEF@morningstar.com; Instid=FINRA; __utmt_MM=1; __utma=93401610.2101660085.1502338375.1502426055.1502432497.7; __utmb=93401610.10.10.1502432497; __utmc=93401610; __utmz=93401610.1502338375.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none); __utmv=93401610.|1=HostSite=finra-markets.morningstar.com=1"));
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    return $content = curl_exec($curl);
}

?>
