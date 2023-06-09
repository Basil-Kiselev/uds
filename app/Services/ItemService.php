<?php

namespace App\Services;

use App\Clients\SkladClient;
use App\Clients\UdsClient;
use App\Models\Item;
use App\Models\Setting;
use GuzzleHttp\Exception\BadResponseException;

require_once "config.php";

class ItemService
{
    public function createItem($data)
    {
        $url = (new UrlItem())->getUrl();
        $setting = Setting::query()->find('1');
        $apiKey = $setting['api_key'];
        $companyId = $setting['company_id'];
        $result = (new UdsClient($companyId, $apiKey))->create($url,$data);
        $localData = ['uds_id' => $result->id];
        Item::query()->create($localData);

        return $result;
    }

    public function getItem($id)
    {
        $setting = Setting::query()->find('1');
        $apiKey = $setting['api_key'];
        $companyId = $setting['company_id'];
        $url = (new UrlItem())->getUrl(). '/' . $id;

        return (new UdsClient($companyId, $apiKey))->get($url);
    }

    public function deleteItem($id)
    {
        Item::query()->where('uds_id', $id)->delete();
        $setting = Setting::query()->find('1');
        $apiKey = $setting['api_key'];
        $companyId = $setting['company_id'];
        $url = (new UrlItem())->getUrl(). '/' . $id;

        return (new UdsClient($companyId, $apiKey))->delete($url);
    }

    public function updateItem($id, $data )
    {
        $url = (new UrlItem())->getUrl(). '/' . $id;
        $setting = Setting::query()->find('1');
        $apiKey = $setting['api_key'];
        $companyId = $setting['company_id'];
        $result = (new UdsClient($companyId, $apiKey))->update($url,$data);
        $localData = ['uds_id' => $result->id];
        Item::query()->where('uds_id', $id)->update($localData);

        return $result;
    }

    public function SkladToUds()
    {
        $setting = Setting::query()->find('1');
        $token = $setting['token'];
        $dataSklad = (new SkladClient($token))->getProducts();
        $dataAll = json_decode($dataSklad->getBody()->getContents());

        try {
            foreach ($dataAll->rows as $data) {
                $inputId = $data->id;
                $skladId = Item::query()->where('sklad_id', $inputId)->value('sklad_id');

                if(!empty($skladId)) continue;
                if(($data->salePrices[0]->value) <= 0) continue;


                $dataBody = [
                    'name' => $data->name,
                    'data' => [
                        'type' => 'ITEM',
                        'price' => ($data->salePrices[0]->value / 100),
                        'sku' => $data->article ?? null,
                        'description' => $data->description ?? null,
                    ]
                ];
                $url = (new UrlItem())->getUrl();
                $setting = Setting::query()->find('1');
                $apiKey = $setting['api_key'];
                $companyId = $setting['company_id'];
                $result = (new UdsClient($companyId, $apiKey))->create($url, $dataBody);
                $localData = [
                    'uds_id' => $result->id,
                    'sklad_id' => $inputId,
                ];
                Item::query()->create($localData);
            }
        }
        catch (BadResponseException $exception) {
            dd($exception->getMessage());
        }

        return true;
    }
}
