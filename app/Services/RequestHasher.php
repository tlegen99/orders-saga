<?php

namespace App\Services;

use Illuminate\Http\Request;

final class RequestHasher
{
    /**
     * генерит канонический SHA256 отпечаток запроса
     * @throws \JsonException
     */
    public function make(Request $request): string
    {
        $bodyData = $request->isJson() ? $request->json()->all() : $request->post();
        $canonicalData = $this->canonicalize($bodyData);
        $canonicalJson = json_encode($canonicalData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stringToHash = $request->method().' '.$request->path()."\n".$canonicalJson;

        return hash('sha256', $stringToHash);
    }

    /**
     * рекурсивная сортировка ключей только у ассоциативных массивов
     */
    private function canonicalize(array $data): array
    {
        if (array_is_list($data)) {
            return array_map(function ($item) {
                return is_array($item) ? $this->canonicalize($item) : $item;
            }, $data);
        }

        ksort($data, SORT_STRING);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->canonicalize($value);
            }
        }

        return $data;
    }
}
