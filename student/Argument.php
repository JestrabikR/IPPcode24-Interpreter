<?php

namespace IPP\Student;
use IPP\Student\ArgType;
use IPP\Core\Interface\OutputWriter;
use IPP\Student\CustomException;
use IPP\Core\ReturnCode;

class Argument {
    public ArgType $argType;
    public mixed $value;
    
    function __construct(ArgType $argType, mixed $value) {
        $this->argType = $argType;
        $this->value = $value;
    }

    /**
     * Sets correct value according to $this->argType into $this->value\
     * Used for setting variable's value
     */
    function setTypedValue(): void {
        if ($this->argType === ArgType::ConstNil) {
            $this->value = Value::NilValue;
        }
        else if ($this->argType === ArgType::ConstBool) {
            $this->value = ($this->value === "true");
        }
        else if ($this->argType === ArgType::ConstInt) {
            $this->value = (int)$this->value;
        }
        else if ($this->argType === ArgType::ConstString) {
            $this->value = $this->value;
        }
        else if ($this->argType === ArgType::Variable) {
            $this->value = $this->value;
        }
        else {
            throw new CustomException("Unknown argType", ReturnCode::INVALID_SOURCE_STRUCTURE);
        }
    }

    function isSymbol(): bool {
        if (($this->argType == ArgType::ConstNil) ||
            ($this->argType == ArgType::ConstBool) ||
            ($this->argType == ArgType::ConstInt) ||
            ($this->argType == ArgType::ConstString) ||
            ($this->argType == ArgType::Variable)) {
                return true;
        }

        return false;
    }

    /**
     * @param OutputWriter $writer (e.g. writer to stdout or stderr)
     * @param mixed $varVal (variable value need to be passed here if printing variable
     *      because the value is stored in Frame which is not in this class)
     */
    function print(OutputWriter $writer, mixed $varVal=""): void {
        switch ($this->argType) {
            case ArgType::ConstBool:
                $writer->writeBool($this->value);
                break;

            case ArgType::ConstInt:
                $writer->writeInt($this->value);
                break;

            case ArgType::ConstString:
                $result = preg_replace_callback('/\\\\\d{3}/', array($this, "replaceEscapeSequences"), $this->value);
                $writer->writeString($result ?? "");
                break;
            
            case ArgType::ConstNil:
                print "";
                break;

            case ArgType::Variable:
                if (is_int($varVal)) {
                    $writer->writeInt($varVal);
                }
                else if (is_string($varVal)) {
                    $result = preg_replace_callback('/\\\\\d{3}/', array($this, "replaceEscapeSequences"), $varVal);
                    $writer->writeString($result ?? "");
                }
                else if (is_bool($varVal)) {
                    $writer->writeBool($varVal);
                }
                else if ($varVal === null) {
                    print "";
                }
                break;
        }

    }

    /**
     * @param array<string> $matches
     */
    private function replaceEscapeSequences(array $matches) : string {
        $escapedChar = $matches[0];
        $asciiValue = (int) ltrim(substr($escapedChar, 1, 3), '0'); // removes '\' and converts to int
        $char = chr($asciiValue);
        return $char;
    }
}
