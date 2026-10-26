@php
    $employee = $application->employee;
    $type = $application->leave_type->value;
    $detail = $application->detail?->value;
    $certification = $application->certification();
    $filedOn = $application->created_at->format('F j, Y');

    // The thirteen types on CS Form No. 6, Revised 2020, in the form's order.
    $types = [
        'VL' => 'Vacation Leave (Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
        'FL' => 'Mandatory/Forced Leave (Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
        'SL' => 'Sick Leave (Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
        'ML' => 'Maternity Leave (R.A. No. 11210 / IRR issued by CSC, DOLE and SSS)',
        'PL' => 'Paternity Leave (R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended)',
        'SPL' => 'Special Privilege Leave (Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
        'SOLO' => 'Solo Parent Leave (RA No. 8972 / CSC MC No. 8, s. 2004)',
        'STUDY' => 'Study Leave (Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
        'VAWC' => '10-Day VAWC Leave (RA No. 9262 / CSC MC No. 15, s. 2005)',
        'RP' => 'Rehabilitation Privilege (Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292)',
        'SLBW' => 'Special Leave Benefits for Women (RA No. 9710 / CSC MC No. 25, s. 2010)',
        'SEL' => 'Special Emergency (Calamity) Leave (CSC MC No. 2, s. 2012, as amended)',
        'ADOPT' => 'Adoption Leave (R.A. No. 8552)',
    ];
    $isOther = ! array_key_exists($type, $types);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave application — {{ $employee->full_name }} — {{ $application->dates() }}</title>
    <style>
        @page { size: A4 portrait; margin: 0.5in 0.35in; }
        * { box-sizing: border-box; }
        html { background: #e5e7eb; }
        body { margin: 0; color: #000; font-family: "Arial Narrow", "Roboto Condensed", "Liberation Sans Narrow", Arial, sans-serif; font-stretch: condensed; font-size: 8pt; line-height: 1.25; }
        p, h1, h2, h3 { margin: 0; }

        .toolbar { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; align-items: center; padding: 12px 16px; font: 14px/1.4 Arial, sans-serif; }
        .toolbar button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
        .scroller { overflow-x: auto; padding: 0 16px 24px; }
        .sheet { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 12.7mm 8.9mm; background: #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, .25); }

        .form { border: 1px solid #000; }
        .head { display: grid; grid-template-columns: 40mm 1fr 46mm; border-bottom: 1px solid #000; }
        .head-left { padding: 1.5mm 2mm; }
        .head-left img { display: block; width: 15mm; height: 15mm; margin-top: 1.5mm; }
        .head-title { display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 3mm; padding: 2mm; border-inline: 1px solid #000; text-align: center; font-weight: bold; }
        .head-title h1 { font-size: 9.5pt; }
        .head-title h2 { font-size: 12.5pt; }
        .doc-box { display: grid; grid-template-rows: repeat(4, 1fr); font-size: 8.5pt; }
        .doc-box p { display: flex; align-items: center; padding: 0.8mm 1.5mm; border-bottom: 1px solid #000; }
        .doc-box p:last-child { border-bottom: 0; }

        .identity { display: grid; grid-template-columns: 35% 65%; padding: 1.2mm 2mm 0; }
        .identity + .identity { padding: 2.5mm 2mm 2mm; border-top: 1px solid #000; }
        .names { display: grid; grid-template-columns: auto 1fr 1fr 1fr; column-gap: 2mm; }
        .names span { text-align: center; }
        .value { min-height: 6mm; padding: 1.5mm 1mm 1mm; text-align: center; font-weight: bold; }
        .inline-fill { display: inline-block; min-width: 30mm; padding: 0 1mm; border-bottom: 1px solid #000; font-weight: bold; text-align: center; }

        .band { padding: 1.2mm; border-block: 1px solid #000; text-align: center; font-size: 9.5pt; font-weight: bold; }
        .split { display: grid; grid-template-columns: 56% 44%; }
        .split + .split { border-top: 1px solid #000; }
        .split > div { padding: 1.5mm 2mm 2mm; }
        .split > div + div { border-left: 1px solid #000; }
        h3 { margin-bottom: 1.6mm; font-size: 8pt; font-weight: normal; }

        .choice { display: flex; align-items: flex-start; gap: 1.8mm; margin: 0 0 1.5mm 3mm; }
        .case { margin: 1.3mm 0 1.2mm 3mm; font-style: italic; }
        .box { flex: none; display: inline-flex; align-items: center; justify-content: center; width: 3.2mm; height: 3.2mm; border: 1px solid #000; font-size: 7pt; font-weight: bold; line-height: 1; }
        .fill { flex: 1; min-height: 3.6mm; padding: 0 1mm; border-bottom: 1px solid #000; font-weight: bold; }
        .indent { margin-left: 8.2mm; }
        .others { display: flex; gap: 1.8mm; margin: 2.5mm 0 1mm 3mm; }
        .others em { flex: none; }
        .line { min-height: 4.2mm; border-bottom: 1px solid #000; }
        .line-block { margin: 1mm 6mm 2.5mm; min-height: 4.5mm; border-bottom: 1px solid #000; text-align: center; font-weight: bold; }
        .caption { text-align: center; }
        .signature { margin: 5mm 6mm 0; text-align: center; }

        .credits { width: calc(100% - 6mm); margin: 1.6mm 0 0 3mm; border-collapse: collapse; }
        .credits th, .credits td { padding: 0.6mm 1.5mm; border: 1px solid #000; text-align: center; font-weight: normal; }
        .credits tbody th { font-style: italic; text-align: left; }
        .signatory { width: 75%; margin: 8mm auto 0; text-align: center; }
        .signatory .name { min-height: 4.5mm; border-bottom: 1px solid #000; font-weight: bold; text-transform: uppercase; }
        .signatory p + p { padding-top: 0.6mm; }
        .short { display: inline-block; min-width: 14mm; padding: 0 1mm; border-bottom: 1px solid #000; font-weight: bold; text-align: center; }
        .approver { padding: 10mm 0 3mm; }
        .approver .signatory { width: 45%; margin-top: 0; }

        @media print {
            html { background: none; }
            .toolbar { display: none; }
            .scroller { overflow: visible; padding: 0; }
            .sheet { width: auto; min-height: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print</button>
        <span>Print on A4, then have it signed.</span>
    </div>

    <div class="scroller">
        <main class="sheet">
            <div class="form">
                <header class="head">
                    <div class="head-left">
                        <p>Civil Service Form No. 6</p>
                        <p>Revised 2020</p>
                        <img src="{{ asset('images/trc-caraga-logo.png') }}" alt="TRC Caraga logo">
                    </div>
                    <div class="head-title">
                        <h1>HUMAN RESOURCE MANAGEMENT SECTION</h1>
                        <h2>LEAVE APPLICATION</h2>
                    </div>
                    <div class="doc-box">
                        <p>Doc. Code : {{ $form['doc_code'] }}</p>
                        <p>Effectivity : {{ $form['effectivity'] }}</p>
                        <p>Revision No. : {{ $form['revision'] }}</p>
                        <p>Page No. : 1 of 1</p>
                    </div>
                </header>

                <section class="identity">
                    <div>
                        <p>1. OFFICE/DEPARTMENT</p>
                        <p class="value">{{ $employee->office?->name }}</p>
                    </div>
                    <div class="names">
                        <p>2. NAME :</p>
                        <span>(Last)</span>
                        <span>(First)</span>
                        <span>(Middle)</span>
                        <span></span>
                        <p class="value">{{ $employee->last_name }}</p>
                        <p class="value">{{ trim($employee->first_name.' '.$employee->suffix) }}</p>
                        <p class="value">{{ $employee->middle_name }}</p>
                    </div>
                </section>
                <section class="identity">
                    <p>3. DATE OF FILING <span class="inline-fill">{{ $filedOn }}</span></p>
                    <p>4. POSITION <span class="inline-fill">{{ $employee->position }}</span> &nbsp; 5. SALARY <span class="inline-fill"></span></p>
                </section>

                <h2 class="band">6. DETAILS OF APPLICATION</h2>
                <section class="split">
                    <div>
                        <h3>6.A TYPE OF LEAVE TO BE AVAILED OF</h3>
                        @foreach ($types as $code => $label)
                            <p class="choice"><x-leave.box :checked="$type === $code" :mark="$code" /><span>{{ $label }}</span></p>
                        @endforeach
                        <p class="others">
                            <em>Others:</em>
                            <span class="fill" @if ($isOther) data-checked="others" @endif>{{ $isOther ? $application->leave_type->getLabel() : '' }}</span>
                        </p>
                    </div>
                    <div>
                        <h3>6.B DETAILS OF LEAVE</h3>
                        <p class="case">In case of Vacation/Special Privilege Leave:</p>
                        <p class="choice"><x-leave.box :checked="$detail === 'within_philippines'" mark="within_philippines" /><span>Within the Philippines</span><span class="fill"></span></p>
                        <p class="choice"><x-leave.box :checked="$detail === 'abroad'" mark="abroad" /><span>Abroad (Specify)</span><span class="fill">{{ $detail === 'abroad' ? $application->detail_text : '' }}</span></p>
                        <p class="case">In case of Sick Leave:</p>
                        <p class="choice"><x-leave.box :checked="$detail === 'in_hospital'" mark="in_hospital" /><span>In Hospital (Specify Illness)</span><span class="fill">{{ $detail === 'in_hospital' ? $application->detail_text : '' }}</span></p>
                        <p class="choice"><x-leave.box :checked="$detail === 'out_patient'" mark="out_patient" /><span>Out Patient (Specify Illness)</span><span class="fill">{{ $detail === 'out_patient' ? $application->detail_text : '' }}</span></p>
                        <p class="case">In case of Special Leave Benefits for Women:</p>
                        <p class="choice indent"><span>(Specify Illness)</span><span class="fill">{{ $type === 'SLBW' ? $application->detail_text : '' }}</span></p>
                        <p class="case">In case of Study Leave:</p>
                        <p class="choice"><x-leave.box :checked="$detail === 'masters_degree'" mark="masters_degree" /><span>Completion of Master's Degree</span></p>
                        <p class="choice"><x-leave.box :checked="$detail === 'board_review'" mark="board_review" /><span>BAR/Board Examination Review</span></p>
                        <p class="case">Other purpose:</p>
                        <p class="choice"><x-leave.box /><span>Monetization of Leave Credits</span></p>
                        <p class="choice"><x-leave.box /><span>Terminal Leave</span></p>
                    </div>
                </section>
                <section class="split">
                    <div>
                        <h3>6.C NUMBER OF WORKING DAYS APPLIED FOR</h3>
                        <p class="line-block">{{ $application->days }}</p>
                        <p class="caption">INCLUSIVE DATES</p>
                        <p class="line-block">{{ $application->dates() }}</p>
                    </div>
                    <div>
                        <h3>6.D COMMUTATION</h3>
                        <p class="choice"><x-leave.box :checked="! $application->commutation_requested" mark="not_requested" /><span>Not Requested</span></p>
                        <p class="choice"><x-leave.box :checked="$application->commutation_requested" mark="requested" /><span>Requested</span></p>
                        <div class="signature">
                            <p class="line"></p>
                            <p>(Signature of Applicant)</p>
                        </div>
                    </div>
                </section>

                <h2 class="band">7. DETAILS OF ACTION ON APPLICATION</h2>
                <section class="split">
                    <div>
                        <h3>7.A CERTIFICATION OF LEAVE CREDITS</h3>
                        <p class="indent">As of <span class="inline-fill">{{ $certification === null ? '' : $filedOn }}</span></p>
                        <table class="credits">
                            <thead>
                                <tr><th></th><th>Vacation Leave</th><th>Sick Leave</th></tr>
                            </thead>
                            <tbody>
                                <tr><th>Total Earned</th><td>{{ $certification['VL']['earned'] ?? '' }}</td><td>{{ $certification['SL']['earned'] ?? '' }}</td></tr>
                                <tr><th>Less this application</th><td>{{ $certification['VL']['less'] ?? '' }}</td><td>{{ $certification['SL']['less'] ?? '' }}</td></tr>
                                <tr><th>Balance</th><td>{{ $certification['VL']['balance'] ?? '' }}</td><td>{{ $certification['SL']['balance'] ?? '' }}</td></tr>
                            </tbody>
                        </table>
                        <div class="signatory">
                            <p class="name">{{ $form['certifying_officer']['name'] }}</p>
                            <p>{{ $form['certifying_officer']['title'] }}</p>
                        </div>
                    </div>
                    <div>
                        <h3>7.B RECOMMENDATION</h3>
                        <p class="choice"><x-leave.box /><span>For approval</span></p>
                        <p class="choice"><x-leave.box /><span>For disapproval due to</span><span class="fill"></span></p>
                        <p class="line indent"></p>
                        <p class="line indent"></p>
                        <p class="line indent"></p>
                        <div class="signatory">
                            <p class="name">{{ $application->division_head }}</p>
                            <p>{{ $form['recommending_title'] }}</p>
                        </div>
                    </div>
                </section>
                <section class="split">
                    <div>
                        <h3>7.C APPROVED FOR:</h3>
                        <p class="indent"><span class="short">{{ $application->days_with_pay }}</span> days with pay</p>
                        <p class="indent"><span class="short">{{ $application->days_without_pay }}</span> days without pay</p>
                        <p class="indent"><span class="short"></span> others (Specify)</p>
                    </div>
                    <div>
                        <h3>7.D DISAPPROVED DUE TO:</h3>
                        <p class="line indent"></p>
                        <p class="line indent"></p>
                        <p class="line indent"></p>
                    </div>
                </section>
                <section class="approver">
                    <div class="signatory">
                        <p class="name">{{ $form['approving_officer']['name'] }}</p>
                        <p>{{ $form['approving_officer']['title'] }}</p>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
