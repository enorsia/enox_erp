{{-- Reusable partial: per-price-row cells for the index table --}}
<td class="px-2 py-2 whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $ch_price->color_code }}</td>
<td class="px-2 py-2 whitespace-nowrap text-slate-700 dark:text-slate-200 font-medium">{{ $ch_price->color_name }}</td>
<td class="px-2 py-2 whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $ch_price->range }}</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-slate-700 dark:text-slate-200">{{ $ch_price->po_order_qty }}</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-slate-700 dark:text-slate-200">$ {{ $ch_price->price_fob }}</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-slate-700 dark:text-slate-200">£ {{ $ch_price->unit_price }}</td>
<td class="px-2 py-2 whitespace-nowrap text-right font-semibold text-slate-800 dark:text-slate-100">£ {{ $ch_price->confirm_selling_price ?? 0 }}</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-slate-600 dark:text-slate-300">£ {{ $ch_price->vat_price ?? 0 }}</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-slate-600 dark:text-slate-300">£ {{ $ch_price->vat_value ?? 0 }}</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-slate-700 dark:text-slate-200">{{ $ch_price->profit_margin ?? 0 }}%</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-slate-700 dark:text-slate-200">£ {{ $ch_price->net_profit ?? 0 }}</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-blue-600 dark:text-blue-400 font-medium">{{ $ch_price->discount ?? 0 }}%</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-blue-600 dark:text-blue-400">£ {{ $ch_price->discount_selling_price ?? 0 }}</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-blue-600 dark:text-blue-400">£ {{ $ch_price->discount_vat_price ?? 0 }}</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-blue-600 dark:text-blue-400">£ {{ $ch_price->discount_vat_value ?? 0 }}</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-blue-600 dark:text-blue-400">{{ $ch_price->discount_profit_margin ?? 0 }}%</td>
<td class="px-2 py-2 whitespace-nowrap text-right text-blue-600 dark:text-blue-400 font-medium">£ {{ $ch_price->discount_net_profit ?? 0 }}</td>

