<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Service;

/**
 * Represents a typed SUUS additional service (e.g. COD, insurance, SMS notification).
 *
 * Implementations return structured field data that the SOAP builder serialises
 * into the correct <additionalService xsi:type="cw:AdditionalService"> complex-type XML.
 *
 * Field-name conventions used by SUUS:
 *   decimal1, decimal2 → xsd:decimal  (PHP float)
 *   varchar1, varchar2 → xsd:string   (PHP string)
 *   bool1, bool2       → xsd:boolean  (PHP bool)
 *   int01              → xsd:string   (WSDL quirk: named int but typed as string)
 *
 * @api
 */
interface ServiceInterface
{
    /** SUUS service symbol, e.g. "RohligCOD", "StdAwizacjaSms". */
    public function getSymbol(): string;

    /**
     * Returns only the non-null additional fields for this service.
     * The array keys are SUUS field names (decimal1, varchar1, bool1, int01, …).
     * The builder infers the xsi:type from the key prefix.
     *
     * @return array<string, bool|float|string>
     */
    public function getSoapFields(): array;
}
