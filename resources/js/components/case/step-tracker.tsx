import { Check, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export type TrackerStep = { key: string; label: string };

type Props = {
    steps: TrackerStep[];
    /** Key of the step the record is on now. */
    current: string;
    /** Marks the current step as stopped (declined, cancelled, rejected). */
    stopped?: boolean;
    className?: string;
};

/**
 * A compact row of steps showing how far a record has moved, for example a
 * proposal going from draft to clinical approval to the patient.
 */
export function StepTracker({
    steps,
    current,
    stopped = false,
    className,
}: Props) {
    const currentIndex = Math.max(
        0,
        steps.findIndex((step) => step.key === current),
    );

    return (
        <ol
            className={cn(
                'flex flex-wrap items-center gap-x-1 gap-y-2',
                className,
            )}
        >
            {steps.map((step, index) => {
                const done =
                    index < currentIndex ||
                    (index === currentIndex &&
                        index === steps.length - 1 &&
                        !stopped);
                const active = index === currentIndex && !done;

                return (
                    <li key={step.key} className="flex items-center gap-1">
                        <span
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap',
                                done && 'bg-brand/10 text-brand',
                                active && !stopped && 'bg-brand text-white',
                                active &&
                                    stopped &&
                                    'bg-rose-100 text-rose-800',
                                !done &&
                                    !active &&
                                    'bg-slate-100 text-slate-500',
                            )}
                            aria-current={active ? 'step' : undefined}
                        >
                            {done ? <Check className="size-3" /> : null}
                            {active && stopped ? (
                                <X className="size-3" />
                            ) : null}
                            {step.label}
                        </span>
                        {index < steps.length - 1 ? (
                            <span
                                className={cn(
                                    'h-px w-3',
                                    index < currentIndex
                                        ? 'bg-brand/40'
                                        : 'bg-slate-200',
                                )}
                                aria-hidden
                            />
                        ) : null}
                    </li>
                );
            })}
        </ol>
    );
}
