<?php

namespace IPP\Student;

use DOMElement;
use InvalidArgumentException;
use IPP\Student\Argument;
use IPP\Student\ArgType;
use IPP\Student\CustomException;
use IPP\Core\ReturnCode;

class InstructionFactory {
    public static function createFromXml(DOMElement $element): Instruction {
        $order = (int) $element->getAttribute("order");
        $opcode = $element->getAttribute("opcode");
        
        /** @var array<Argument> $arguments*/
        $arguments = array();

        // parse arguments
        foreach ($element->childNodes as $node) {
            if ($node instanceof DOMElement) {
                if (preg_match('/^arg\d+$/', $node->nodeName) !== 1) {
                    throw new CustomException("Wrong argument name", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }

                $argType = $node->getAttribute("type");
                switch ($argType) {
                    case "int":
                        $argType = ArgType::ConstInt;
                        break;
                    case "bool":
                        $argType = ArgType::ConstBool;
                        break;
                    case "string":
                        $argType = ArgType::ConstString;
                        break;
                    case "nil":
                        $argType = ArgType::ConstNil;
                        break;
                    case "label":
                        $argType = ArgType::Label;
                        break;
                    case "var":
                        $argType = ArgType::Variable;
                        break;
                    case "type":
                        $argType = ArgType::Type;
                        break;
                    default:
                        throw new CustomException("Unknown argType", ReturnCode::INVALID_SOURCE_STRUCTURE);
                }
                
                $argValue = trim($node->nodeValue ?? "");

                $newArg = new Argument($argType, $argValue);

                if ($newArg->isSymbol()) {
                    // sets value to correct type
                    $newArg->setTypedValue();
                }

                array_push($arguments, $newArg);
            }
        }

        return new Instruction($order, $opcode, $arguments);
    }
}
