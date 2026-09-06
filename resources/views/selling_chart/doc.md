# 	EA24-W-P310602 Selling Chart Calculation

## User Inputs

The following values are entered by the user:

| Field | Description |
|-------|-------------|ssssssssss
| PO Qty | Purchase order quantity |
| FOB ($) | FOB price in USD |
| Shipping | Shipping cost per unit |
| CSP (£) | Confirmed Selling Price |
| Discount (%) | Discount percentage |

---

# 1. Unit Price Calculation

The unit price is calculated using the following formula:

```javascript
const unitPrice = priceFOB
    ? (priceFOB * conversionRate) +
      (
          commercialExpense +
          enorsiaBDExpense +
          enorsiaUKExpense +
          shippingCost
      )
    : 0;
```

### Formula

```
Unit Price =
(FOB × Conversion Rate)
+ Commercial Expense
+ Enorsia BD Expense
+ Enorsia UK Expense
+ Shipping Cost
```

---

# 2. VAT Calculation

VAT is applied only for departments:

- **1926**
- **1927**

For these departments:

```
VAT Value = CSP × 20 / 120

Selling Price (Excluding VAT)
= CSP − VAT Value
```

For all other departments:

```
VAT Value = 0

Selling Price (Excluding VAT) = CSP
```

Reference implementation:

```javascript
const csp = parseFloat($row.find('.confirm_selling_price').val()) || 0;

let sellingVat;
let sellingVatValue;

if (department == 1926 || department == 1927) {
    sellingVatValue = (csp * 20) / 120;
    sellingVat = csp - sellingVatValue;
} else {
    sellingVatValue = 0;
    sellingVat = csp;
}
```

---

# 3. Profit Margin Calculation

```
Profit Margin (%) =
((Selling Price Excluding VAT − Unit Price)
÷ Selling Price Excluding VAT)
× 100
```

```javascript
$row.find('.profit_margin').val(
    sellingVat
        ? ((sellingVat - unitPrice) / sellingVat * 100).toFixed(2)
        : '0.00'
);
```

---

# 4. Net Profit Calculation

```
Net Profit =
Selling Price Excluding VAT − Unit Price
```

```javascript
$row.find('.net_profit').val(
    (sellingVat - unitPrice).toFixed(2)
);
```

---

# 5. Discount Calculation

```
Discount Selling Price =
CSP − (CSP × Discount %)
```

Reference implementation:

```javascript
const discount = parseFloat($row.find('.discount').val()) || 0;

const discountSellingPrice =
    csp - (csp * (discount / 100));

$row.find('.discount_selling_price')
    .val(discountSellingPrice.toFixed(2));
```

---

# 6. Discount VAT Calculation

For departments **1926** and **1927**:

```
Discount Selling Price (Excluding VAT)
= Discount Selling Price ÷ 120 × 100

Discount VAT Value
= Discount Selling Price − Discount Selling Price (Excluding VAT)
```

For all other departments:

```
Discount Selling Price (Excluding VAT)
= Discount Selling Price

Discount VAT Value = 0
```

Reference implementation:

```javascript
let sellingVatDedactPrice;
let discountVatValue;

if (department == 1926 || department == 1927) {
    sellingVatDedactPrice =
        (discountSellingPrice / 120) * 100;

    discountVatValue =
        discountSellingPrice - sellingVatDedactPrice;
} else {
    sellingVatDedactPrice = discountSellingPrice;
    discountVatValue = 0;
}

$row.find('.selling_vat_dedact_price')
    .val(sellingVatDedactPrice.toFixed(2));

$row.find('.discount_vat_value')
    .val(discountVatValue.toFixed(2));
```

---

# 7. Discount Profit Margin

```
Discount Profit Margin (%) =
(
(Discount Selling Price Excluding VAT − Unit Price)
÷ Discount Selling Price Excluding VAT
)
× 100
```

```javascript
$row.find('.discount_profit_margin').val(
    sellingVatDedactPrice
        ? (
            (sellingVatDedactPrice - unitPrice)
            / sellingVatDedactPrice
            * 100
        ).toFixed(2)
        : '0.00'
);
```

---

# 8. Discount Net Profit

```
Discount Net Profit =
Discount Selling Price Excluding VAT − Unit Price
```

```javascript
$row.find('.discount_net_profit').val(
    (sellingVatDedactPrice - unitPrice).toFixed(2)
);
```

---

# Summary

## User Inputs

- PO Qty
- FOB ($)
- Shipping
- Confirm Selling Price (CSP)
- Discount %

## Calculated Outputs

- Unit Price
- VAT
- VAT Value
- Profit Margin
- Net Profit
- Discount Selling Price
- Discount VAT
- Discount VAT Value
- Discount Profit Margin
- Discount Net Profit
