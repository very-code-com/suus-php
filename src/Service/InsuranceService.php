<?php

declare(strict_types=1);

namespace VeryCodeCom\Suus\Service;

/**
 * Goods insurance additional service.
 *
 * SUUS symbol: RohligUbezpieczenie3
 * Covers damage and loss. Three goods-type categories are supported.
 *
 * @api
 */
final class InsuranceService implements ServiceInterface
{
    /** Standard goods (towary zwykłe). */
    public const GOODS_STANDARD  = 'UB_POZ';
    /** Pharmaceutical goods (farmaceutyki). */
    public const GOODS_PHARMA    = 'UB_LEK';
    /** Temperature-sensitive goods (towary wymagające temp.). */
    public const GOODS_TEMP      = 'UB_TEMP';

    /**
     * @param float       $amount          Declared goods value (PLN).
     * @param string      $goodsType       One of GOODS_STANDARD, GOODS_PHARMA, GOODS_TEMP.
     * @param float       $additionalCosts Additional transport costs to insure (PLN).
     * @param bool        $strikeClause    Include strike clause.
     * @param bool        $warClause       Include war clause.
     * @param string|null $goodsDeclaration Goods declaration number (sent as int01, typed xsd:string per WSDL).
     */
    public function __construct(
        public readonly float       $amount,
        public readonly string      $goodsType         = self::GOODS_STANDARD,
        public readonly float       $additionalCosts   = 0.0,
        public readonly bool        $strikeClause      = false,
        public readonly bool        $warClause         = false,
        public readonly ?string     $goodsDeclaration  = null,
    ) {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('InsuranceService amount must be > 0.');
        }
        if (!in_array($goodsType, [self::GOODS_STANDARD, self::GOODS_PHARMA, self::GOODS_TEMP], true)) {
            throw new \InvalidArgumentException(
                "InsuranceService goodsType must be one of: UB_POZ, UB_LEK, UB_TEMP. Got: {$goodsType}."
            );
        }
    }

    public function getSymbol(): string
    {
        return 'RohligUbezpieczenie3';
    }

    public function getSoapFields(): array
    {
        $fields = [
            'decimal1' => $this->amount,
            'decimal2' => $this->additionalCosts,
            'varchar1' => 'PLN',
            'varchar2' => $this->goodsType,
            'bool1'    => $this->strikeClause,
            'bool2'    => $this->warClause,
        ];

        if ($this->goodsDeclaration !== null) {
            // int01 is xsd:string per WSDL despite its name
            $fields['int01'] = $this->goodsDeclaration;
        }

        return $fields;
    }
}
