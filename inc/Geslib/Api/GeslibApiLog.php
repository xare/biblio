<?php

namespace Inc\Geslib\Api;

use Inc\Geslib\Api\GeslibApiDbLogManager;


class GeslibApiLog {

    public function getQueuedFile(){
        $geslibApiDbLogManager = new GeslibApiDbLogManager;
        return $geslibApiDbLogManager->getLogQueuedFile();
    }

    public function setStatus( $log_id, $status ){
        $geslibApiDbLogManager = new GeslibApiDbLogManager;
        return $geslibApiDbLogManager->setLogStatus( $log_id, $status );
    }


}