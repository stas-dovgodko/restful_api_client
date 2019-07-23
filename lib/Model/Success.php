<?php

namespace JsonAPI\Model;

use JsonAPI\Model;

class Success extends Model {

    /**
     * @param array $data
     * @return Success
     * @throws Exception\UnsuccessfulException
     */
    public static function FromArray(array $data)
    {
        $object = parent::FromArray($data);

        if (!array_key_exists('success', $data) || !$data['success']) {
            throw new Exception\UnsuccessfulException(
                array_key_exists('error', $data) ? $data['error'] : '<Empty>'
                , $object);
        } elseif (array_key_exists('error', $data)) {
            throw new Exception\UnsuccessfulException(
                $data['error']
                , $object);
        }

        return $object;
    }
}
