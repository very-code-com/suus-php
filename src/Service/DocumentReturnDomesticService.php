<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Service;

/**
 * Return of documents to sender - domestic variant (KR).
 *
 * SUUS symbol: StdDokumentyZwrotneINiezwrotneGrid2
 * Available for domestic orders only (WS PK 1.0). Using it on an international
 * order is rejected by {@see \VeryCodeCom\Suus\Internal\Validator\ShipmentValidator}
 * unless the service route restrictions are relaxed via
 * {@see \VeryCodeCom\Suus\Validation\ValidationPolicy}.
 *
 * @api
 */
final class DocumentReturnDomesticService extends AbstractDocumentReturnService
{
    public function getSymbol(): string
    {
        return 'StdDokumentyZwrotneINiezwrotneGrid2';
    }
}
