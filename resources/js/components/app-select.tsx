import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

type Option = { value: string; label: string };

type Props = {
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    id?: string;
    placeholder?: string;
    /** Adds a first choice whose value is '' (for example "All"). */
    emptyLabel?: string;
    disabled?: boolean;
    className?: string;
    'aria-label'?: string;
};

/** The styled dropdowns cannot hold an empty value, so '' maps to this. */
const EMPTY = '__empty__';

/**
 * The app's styled dropdown with the simple API of a native select: a value,
 * a change handler and a list of options. Use it instead of `<select>`.
 */
export function AppSelect({
    value,
    onChange,
    options,
    id,
    placeholder,
    emptyLabel,
    disabled = false,
    className,
    'aria-label': ariaLabel,
}: Props) {
    const current = value === '' && emptyLabel !== undefined ? EMPTY : value;

    return (
        <Select
            value={current === '' ? undefined : current}
            onValueChange={(next) => onChange(next === EMPTY ? '' : next)}
            disabled={disabled}
        >
            <SelectTrigger id={id} aria-label={ariaLabel} className={cn('w-full', className)}>
                <SelectValue placeholder={placeholder ?? emptyLabel ?? 'Choose…'} />
            </SelectTrigger>
            <SelectContent>
                {emptyLabel !== undefined ? (
                    <SelectItem value={EMPTY}>{emptyLabel}</SelectItem>
                ) : null}
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
