<?php

namespace IRC;

abstract class State {
    const DISCONNECTED = 0;
    const CONNECTING = 1;
    /**
     * sending NICK USER
     */
    const REGISTERING = 2;
    /**
     * Connected and have received the 001 welcome
     */
    const CONNECTED = 3;
    /**
     * Authenticated with AuthServ
     */
    const AUTHED = 4;
}
