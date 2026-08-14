<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Service;

/**
 * Shared implementation for the "return of documents to sender" services
 * (SUUS: "Zwrot dokumentów do nadawcy"). Two concrete variants exist, differing
 * only by symbol and route availability:
 *
 *   - {@see DocumentReturnDomesticService}      (StdDokumentyZwrotneINiezwrotneGrid2) - domestic only
 *   - {@see DocumentReturnInternationalService} (StdDokumentyZwrotneINiezwrotneGrid3) - international only
 *
 * SUUS fields (WS PK 1.0):
 *   int01=1, varchar1=document number, varchar2=tag (DZ/DT),
 *   varchar3=document type (FK/WZ/ZLEC/SPEC), varchar4=description.
 *
 * @api
 */
abstract class AbstractDocumentReturnService implements ServiceInterface
{
    /** Return documents - checks the ROD box (dokumenty zwrotne). */
    public const TAG_RETURN       = 'DZ';
    /** Accompanying documents - checks the eROD box (dokumenty towarzyszące). */
    public const TAG_ACCOMPANYING = 'DT';

    /** Invoice (Faktura). */
    public const DOC_INVOICE        = 'FK';
    /** Goods-issued note (Wz). */
    public const DOC_WZ             = 'WZ';
    /** Shipping order (Zlecenie Spedycyjne). */
    public const DOC_SHIPPING_ORDER = 'ZLEC';
    /** Order specification (Specyfikacja zamówienia). */
    public const DOC_SPECIFICATION  = 'SPEC';

    private const TAGS           = [self::TAG_RETURN, self::TAG_ACCOMPANYING];
    private const DOCUMENT_TYPES = [self::DOC_INVOICE, self::DOC_WZ, self::DOC_SHIPPING_ORDER, self::DOC_SPECIFICATION];

    /**
     * @param string $documentNumber Document number (varchar1).
     * @param string $tag            One of TAG_RETURN ('DZ') or TAG_ACCOMPANYING ('DT').
     * @param string $documentType   One of DOC_INVOICE, DOC_WZ, DOC_SHIPPING_ORDER, DOC_SPECIFICATION.
     * @param string $description     Optional free-text description (varchar4).
     */
    public function __construct(
        public readonly string $documentNumber,
        public readonly string $tag          = self::TAG_RETURN,
        public readonly string $documentType = self::DOC_INVOICE,
        public readonly string $description  = '',
    ) {
        if (trim($documentNumber) === '') {
            throw new \InvalidArgumentException(static::class . ': documentNumber must not be empty.');
        }
        if (!in_array($tag, self::TAGS, true)) {
            throw new \InvalidArgumentException(
                static::class . ": tag must be one of DZ, DT. Got: {$tag}."
            );
        }
        if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
            throw new \InvalidArgumentException(
                static::class . ": documentType must be one of FK, WZ, ZLEC, SPEC. Got: {$documentType}."
            );
        }
    }

    public function getSoapFields(): array
    {
        $fields = [
            'int01'    => '1',
            'varchar1' => $this->documentNumber,
            'varchar2' => $this->tag,
            'varchar3' => $this->documentType,
        ];

        if ($this->description !== '') {
            $fields['varchar4'] = $this->description;
        }

        return $fields;
    }
}
