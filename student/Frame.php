<?php

namespace IPP\Student;

class Frame {
    /** @var array<string, mixed> $dict */
    public array $dict;
    public bool $isInitialized;
    
    /**
     * @param array<string, mixed> $dict
     * @param bool $isInitialized
    */
    function __construct(array $dict=array(), bool $isInitialized=false) {
        $this->dict = $dict;
        $this->isInitialized = $isInitialized;
    }

}