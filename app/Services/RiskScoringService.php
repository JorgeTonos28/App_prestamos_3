<?php

namespace App\Services;

use App\Models\LoanApplication;
use Carbon\Carbon;

class RiskScoringService
{
    public function __construct(private readonly WhatsAppAgentSettings $settings) {}

    public function score(LoanApplication $application): array
    {
        $applicant = $application->applicant_data ?? [];
        $loan = $application->loan_request ?? [];
        $factors = [];
        $redFlags = [];
        $mitigants = [];
        $score = 0.0;

        $income = max(0, (float) ($applicant['monthly_income'] ?? 0));
        $expenses = max(0, (float) ($applicant['monthly_expenses'] ?? 0));
        $debtPayments = max(0, (float) ($applicant['monthly_debt_payments'] ?? 0));
        $installment = max(0, (float) ($loan['preferred_installment'] ?? 0));
        $requested = max(0, (float) ($loan['requested_amount'] ?? 0));

        if (! $application->consent_at) {
            $this->factor($factors, $score, 'missing_consent', 'Falta consentimiento verificable', 100, 'No existe sello de tiempo de consentimiento.');
            $redFlags[] = 'No hay consentimiento de tratamiento de datos registrado.';
        }

        $age = $this->age($applicant['birth_date'] ?? null);
        if ($age === null || $age < 18) {
            $this->factor($factors, $score, 'invalid_age', 'Edad no verificable o menor de edad', 100, 'La fecha de nacimiento no cumple la política mínima.');
            $redFlags[] = 'La edad requiere verificación obligatoria.';
        }

        $minimumIncome = max(0, (float) $this->settings->get('risk_min_monthly_income', 15000));
        if ($income <= 0) {
            $this->factor($factors, $score, 'no_income', 'Ingreso no demostrable', 35, 'El ingreso mensual declarado es cero o no está disponible.');
            $redFlags[] = 'No existe capacidad de pago declarada.';
        } elseif ($income < $minimumIncome) {
            $this->factor($factors, $score, 'income_below_policy', 'Ingreso por debajo de la política', 15, "Ingreso declarado inferior a RD$ {$minimumIncome}.");
        } else {
            $mitigants[] = 'El ingreso declarado supera el mínimo configurado.';
        }

        $debtToIncome = $income > 0 ? $debtPayments / $income : null;
        $maxDebtToIncome = max(0.01, (float) $this->settings->get('risk_max_debt_to_income', 0.40));
        if ($debtToIncome !== null && $debtToIncome > $maxDebtToIncome) {
            $points = min(25, 10 + (($debtToIncome - $maxDebtToIncome) * 50));
            $this->factor($factors, $score, 'high_dti', 'Alta carga de deuda actual', $points, 'Relación deuda/ingreso: '.$this->percentage($debtToIncome).'.');
            $redFlags[] = 'La carga mensual de otras deudas supera el límite configurado.';
        } elseif ($debtToIncome !== null) {
            $mitigants[] = 'La relación deuda/ingreso declarada está dentro del límite configurado.';
        }

        $installmentToIncome = $income > 0 ? $installment / $income : null;
        $maxInstallmentToIncome = max(0.01, (float) $this->settings->get('risk_max_installment_to_income', 0.35));
        if ($installmentToIncome !== null && $installmentToIncome > $maxInstallmentToIncome) {
            $points = min(25, 10 + (($installmentToIncome - $maxInstallmentToIncome) * 50));
            $this->factor($factors, $score, 'high_installment_ratio', 'Cuota propuesta elevada', $points, 'Relación cuota/ingreso: '.$this->percentage($installmentToIncome).'.');
            $redFlags[] = 'La cuota máxima propuesta supera la proporción de ingreso permitida.';
        }

        $totalCommitments = $expenses + $debtPayments + $installment;
        $cashFlowRatio = $income > 0 ? $totalCommitments / $income : null;
        $disposableIncome = $income - $totalCommitments;
        if ($cashFlowRatio !== null && $cashFlowRatio > 1) {
            $this->factor($factors, $score, 'negative_cash_flow', 'Flujo mensual negativo', 30, 'Los compromisos declarados superan el ingreso.');
            $redFlags[] = 'El flujo mensual después de gastos, deudas y cuota sería negativo.';
        } elseif ($cashFlowRatio !== null && $cashFlowRatio > 0.85) {
            $this->factor($factors, $score, 'thin_cash_buffer', 'Margen mensual reducido', 15, 'Los compromisos consumirían '.$this->percentage($cashFlowRatio).' del ingreso.');
        } elseif ($disposableIncome > 0) {
            $mitigants[] = 'La declaración conserva flujo disponible después de la cuota propuesta.';
        }

        $loanToIncome = $income > 0 ? $requested / $income : null;
        $maxLoanToIncome = max(0.1, (float) $this->settings->get('risk_max_loan_to_monthly_income', 6));
        if ($loanToIncome !== null && $loanToIncome > $maxLoanToIncome) {
            $this->factor($factors, $score, 'high_loan_to_income', 'Monto solicitado alto respecto al ingreso', 15, 'El monto equivale a '.round($loanToIncome, 2).' ingresos mensuales.');
        }

        $employmentStatus = (string) ($applicant['employment_status'] ?? '');
        if ($employmentStatus === 'unemployed') {
            $this->factor($factors, $score, 'unemployed', 'Sin empleo declarado', 20, 'La persona indicó estar desempleada.');
            $redFlags[] = 'No se declaró una fuente laboral activa.';
        } elseif (in_array($employmentStatus, ['student'], true)) {
            $this->factor($factors, $score, 'limited_employment', 'Fuente laboral limitada', 10, 'La situación declarada requiere confirmar otra fuente de ingresos.');
        }

        $minimumEmploymentMonths = max(0, (int) $this->settings->get('risk_min_employment_months', 6));
        $employmentMonths = max(0, (int) ($applicant['employment_tenure_months'] ?? 0));
        if (in_array($employmentStatus, ['employed', 'self_employed'], true) && $employmentMonths < $minimumEmploymentMonths) {
            $this->factor($factors, $score, 'short_employment_tenure', 'Antigüedad laboral reducida', 10, "Antigüedad declarada: {$employmentMonths} meses.");
        } elseif ($employmentMonths >= 24) {
            $score -= 5;
            $mitigants[] = 'La antigüedad laboral o de actividad declarada es de al menos 24 meses.';
        }

        $documentAnalysis = $this->documentSignals($application);
        foreach ($documentAnalysis['factors'] as $factor) {
            $factors[] = $factor;
            $score += $factor['points'];
        }
        $redFlags = [...$redFlags, ...$documentAnalysis['red_flags']];
        $mitigants = [...$mitigants, ...$documentAnalysis['mitigants']];

        $score = round(max(0, min(100, $score)), 2);
        $lowMax = max(0, min(99, (float) $this->settings->get('risk_low_max_score', 35)));
        $mediumMax = max($lowMax, min(100, (float) $this->settings->get('risk_medium_max_score', 65)));
        $critical = collect($factors)->contains(fn (array $factor): bool => $factor['points'] >= 40);

        $level = $critical || $score > $mediumMax
            ? 'high'
            : ($score > $lowMax ? 'medium' : 'low');
        $recommendation = match ($level) {
            'low' => 'consider_approval',
            'medium' => 'manual_review',
            default => 'decline_or_mitigate',
        };

        return [
            'score' => $score,
            'level' => $level,
            'recommendation' => $recommendation,
            'factors' => $factors,
            'red_flags' => array_values(array_unique($redFlags)),
            'mitigants' => array_values(array_unique($mitigants)),
            'metrics' => [
                'monthly_income' => $income,
                'monthly_expenses' => $expenses,
                'monthly_debt_payments' => $debtPayments,
                'proposed_installment' => $installment,
                'disposable_income_after_proposed_installment' => round($disposableIncome, 2),
                'debt_to_income' => $debtToIncome === null ? null : round($debtToIncome, 4),
                'installment_to_income' => $installmentToIncome === null ? null : round($installmentToIncome, 4),
                'total_commitments_to_income' => $cashFlowRatio === null ? null : round($cashFlowRatio, 4),
                'loan_to_monthly_income' => $loanToIncome === null ? null : round($loanToIncome, 4),
                'age' => $age,
                'employment_tenure_months' => $employmentMonths,
            ],
        ];
    }

    private function documentSignals(LoanApplication $application): array
    {
        $factors = [];
        $redFlags = [];
        $mitigants = [];
        $required = collect($application->required_documents ?? [])->pluck('key');
        $documents = $application->documents;
        $validTypes = $documents->where('status', 'valid')->pluck('document_type');

        foreach ($required->diff($validTypes) as $missing) {
            $factors[] = [
                'code' => 'missing_document_'.$missing,
                'label' => 'Documento requerido no validado',
                'points' => 15.0,
                'evidence' => "Falta validar el documento {$missing}.",
            ];
            $redFlags[] = "Falta validar un documento requerido: {$missing}.";
        }

        if ($required->isNotEmpty() && $required->diff($validTypes)->isEmpty()) {
            $mitigants[] = 'Todos los documentos configurados como obligatorios fueron recibidos y superaron la validación estructural.';
        }

        foreach ($documents->where('status', 'valid') as $document) {
            $validation = $document->validation_results ?? [];
            if (($validation['suspected_manipulation'] ?? false) === true) {
                $factors[] = [
                    'code' => 'document_manipulation_signal',
                    'label' => 'Señal documental de manipulación',
                    'points' => 50.0,
                    'evidence' => "El documento {$document->label} contiene una señal de revisión crítica.",
                ];
                $redFlags[] = 'Existe una señal automatizada de posible manipulación documental; requiere verificación humana.';
            }

            $warnings = $validation['warnings'] ?? [];
            if (is_array($warnings) && $warnings !== []) {
                $points = min(10, count($warnings) * 2);
                $factors[] = [
                    'code' => 'document_warnings_'.$document->id,
                    'label' => 'Advertencias documentales',
                    'points' => (float) $points,
                    'evidence' => count($warnings).' advertencia(s) en '.$document->label.'.',
                ];
            }

            $creditScore = $this->extractCreditScore($validation['extracted_facts'] ?? []);
            if ($creditScore !== null) {
                if ($creditScore < 500) {
                    $this->appendDocumentFactor($factors, $redFlags, 'very_low_credit_score', 'Score crediticio muy bajo', 30, "Score extraído: {$creditScore}.");
                } elseif ($creditScore < 600) {
                    $this->appendDocumentFactor($factors, $redFlags, 'low_credit_score', 'Score crediticio bajo', 20, "Score extraído: {$creditScore}.");
                } elseif ($creditScore < 680) {
                    $factors[] = ['code' => 'fair_credit_score', 'label' => 'Score crediticio moderado', 'points' => 8.0, 'evidence' => "Score extraído: {$creditScore}."];
                } elseif ($creditScore >= 720) {
                    $mitigants[] = 'El score crediticio extraído es favorable (720 o más), sujeto a verificación humana.';
                }
            }
        }

        return [
            'factors' => $factors,
            'red_flags' => $redFlags,
            'mitigants' => $mitigants,
        ];
    }

    private function extractCreditScore(array $facts): ?int
    {
        foreach ($facts as $fact) {
            $name = strtolower((string) ($fact['name'] ?? ''));
            if (! str_contains($name, 'score') && ! str_contains($name, 'puntaje')) {
                continue;
            }

            if (preg_match('/\b([3-8]\d{2})\b/', (string) ($fact['value'] ?? ''), $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private function factor(array &$factors, float &$score, string $code, string $label, float $points, string $evidence): void
    {
        $points = round($points, 2);
        $factors[] = compact('code', 'label', 'points', 'evidence');
        $score += $points;
    }

    private function appendDocumentFactor(array &$factors, array &$redFlags, string $code, string $label, float $points, string $evidence): void
    {
        $factors[] = compact('code', 'label', 'points', 'evidence');
        $redFlags[] = $label.'; el dato debe verificarse contra la fuente original.';
    }

    private function age(?string $birthDate): ?int
    {
        try {
            return $birthDate ? Carbon::parse($birthDate)->age : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function percentage(float $ratio): string
    {
        return number_format($ratio * 100, 1).'%';
    }
}
