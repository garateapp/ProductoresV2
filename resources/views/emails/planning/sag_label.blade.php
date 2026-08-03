<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiqueta SAG</title>
</head>
<body style="margin:0;padding:8px;background:#ffffff;font-family:'Courier New',Courier,monospace;color:#000000;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:10cm;height:5cm;max-width:378px;min-width:378px;min-height:189px;margin:0 auto;border-collapse:collapse;table-layout:fixed;background:#ffffff;border:1px solid #000000;">
        <tr>
            <td style="padding:0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:10cm;height:5cm;max-width:378px;min-width:378px;min-height:189px;border-collapse:collapse;table-layout:fixed;">
                    <tr style="height:26px;">
                        <td style="width:142px;padding:3px 5px;border-bottom:1px solid #000000;font-size:10px;font-weight:700;line-height:1.05;">
                            {{ $label['species'] }}
                        </td>
                        <td style="width:94px;padding:2px 4px;border-left:1px solid #000000;border-bottom:1px solid #000000;text-align:center;vertical-align:middle;">
                            <div style="font-size:6px;line-height:1;">Caja</div>
                            <div style="margin-top:1px;font-size:12px;font-weight:700;line-height:1;">{{ $label['box_number'] }}</div>
                        </td>
                        <td style="width:142px;padding:3px 5px;border-left:1px solid #000000;border-bottom:1px solid #000000;text-align:right;font-size:10px;font-weight:700;line-height:1.05;">
                            {{ $label['variety'] }}
                        </td>
                    </tr>
                    <tr style="height:92px;">
                        <td style="width:142px;padding:4px 5px;vertical-align:top;border-bottom:1px solid #000000;font-size:6px;line-height:1.05;">
                            <div>Grower / Productor: <strong>{{ $label['producer_name'] }}</strong></div>
                            <div style="margin-top:3px;font-size:9px;font-weight:700;">CSG: {{ $label['csg_code'] }}</div>
                            <div style="margin-top:2px;">GGN: {{ $label['ggn_code'] }}</div>
                            <div style="margin-top:2px;font-size:8px;font-weight:700;">SDP: {{ $label['sdp_code'] }}</div>
                            <div style="margin-top:3px;">Township / Comuna:</div>
                            <div>{{ $label['township'] }}</div>
                            <div style="margin-top:2px;">Province / Provincia:</div>
                            <div>{{ $label['province'] }}</div>
                            <div style="margin-top:2px;">Region: {{ $label['region'] }}</div>
                        </td>
                        <td style="width:94px;padding:4px 4px;vertical-align:top;border-left:1px solid #000000;border-bottom:1px solid #000000;font-size:6px;line-height:1.05;">
                            <div>Packed by / Embalado por:</div>
                            <div style="margin-top:3px;">Comercializadora</div>
                            <div>{{ $label['packing_name'] ?? '-' }}</div>
                            <div style="margin-top:2px;">CSP: {{ $label['packing_code'] ?? '-' }}</div>
                            <div style="margin-top:3px;">Township / Comuna:</div>
                            <div>{{ $label['packing_township'] ?? '-' }}</div>
                            <div style="margin-top:2px;">Province / Provincia:</div>
                            <div>{{ $label['packing_province'] ?? '-' }}</div>
                            <div style="margin-top:2px;">Region: {{ $label['packing_region'] ?? '-' }}</div>
                        </td>
                        <td style="width:142px;padding:4px 5px;vertical-align:top;border-left:1px solid #000000;border-bottom:1px solid #000000;font-size:6px;line-height:1.05;">
                            <div>Calibre / Size</div>
                            <div style="margin-top:2px;font-size:16px;font-weight:700;line-height:0.95;">{{ $label['size_code'] }}</div>
                            <div style="margin-top:5px;font-size:7px;">{{ $label['packaging_code'] }}</div>
                            <div style="margin-top:2px;font-size:7px;">{{ $label['label_name'] }}</div>
                            <div style="margin-top:3px;">Net Wt/Peso: {{ $label['net_weight'] }} Kg</div>
                            <div style="margin-top:2px;">Cat: {{ $label['category'] }}</div>
                        </td>
                    </tr>
                    <tr style="height:28px;">
                        <td style="width:142px;padding:3px 5px;vertical-align:top;border-bottom:1px solid #000000;font-size:6px;line-height:1.05;">
                            <div>LOTE: <strong>{{ $label['lot_number'] }}</strong></div>
                            <div style="margin-top:2px;">Proc: <strong>{{ $label['process_name'] }}</strong></div>
                        </td>
                        <td colspan="2" style="padding:0;vertical-align:top;border-left:1px solid #000000;border-bottom:1px solid #000000;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;table-layout:fixed;">
                                <tr>
                                    <td style="width:70px;padding:3px 3px;font-size:6px;line-height:1.05;vertical-align:top;">
                                        <div>{{ $label['output'] }}</div>
                                        <div style="margin-top:2px;">{{ $label['line_name'] }}</div>
                                        <div style="margin-top:2px;font-size:7px;font-weight:700;">{{ $label['shift_name'] }}</div>
                                    </td>
                                    <td style="width:70px;padding:3px 3px;font-size:6px;line-height:1.05;text-align:center;vertical-align:top;">
                                        <div>When Packed</div>
                                        <div style="margin-top:6px;">Date / Fecha</div>
                                    </td>
                                    <td style="padding:3px 3px;font-size:7px;line-height:1.05;text-align:center;vertical-align:top;">
                                        <div style="font-weight:700;">{{ $label['packed_date'] }}</div>
                                        <div style="margin-top:8px;">{{ $label['packed_time'] }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr style="height:39px;">
                        <td colspan="3" style="padding:4px 6px;vertical-align:top;font-size:6px;line-height:1.05;">
                            <div>Exported by / Exportado por:</div>
                            <div style="margin-top:3px;font-size:9px;font-weight:700;">{{ $label['exporter_name'] }}</div>
                            <div style="margin-top:2px;">CSE: {{ $label['exporter_code'] }}</div>
                            <div style="margin-top:2px;">Produce Of Chile / Producto De Chile</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
