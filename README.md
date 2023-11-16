<b>Installation :</b>
composer require gauravcape/m2-csv-import-api
<b>Note :</b> tested with composer version 2 for dowloading through composer, otherwise you can dowload it directly

<br><b>OverView :</b>

This module implement WebAPI for product import via api through Magento CSV

<b>API Information:</b>
<table>
    <thead>
        <tr>
            <th>Resource</th>
            <th>Request method</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{base_url}/rest/default/V1/csvproductimport</td>
            <td>POST</td>
            <td>Import Products by csv through API</td>
        </tr>
    </tbody>
</table>
<b>Note :</b> at Api endpoint, default can be replaced as per you store view

<br><b>API Body Payload are as :</b>
<br>{
    <br>"csv_path_type": "url",<br>
    "csv_location": ""<br>
}

<b>Information about payload:</b><br>
<b>a.</b> <b>csv_path_type</b> can have value : "url" or "local" <br>
<b>b.</b> <b>csv_location</b> can have value : url of csv file or local server path

