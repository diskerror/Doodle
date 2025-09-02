<?php

namespace Library;

use Library\Exceptions\SQLite3Exception;

class SQLite3 extends \SQLite3
{
    public function __destruct()
    {
        if(!$this->close()){
            throw new SQLite3Exception('SQLite3::close() returned false');
        }
    }
}
