import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/** Auswahlfeld für eine Liste aus config/intranet.php (Schlüssel => Bezeichnung). */
export default function SelectField({
    id,
    value,
    options,
    onChange,
    placeholder = 'Bitte wählen',
    invalid,
}: {
    id: string;
    value: string;
    options: Record<string, string>;
    onChange: (value: string) => void;
    placeholder?: string;
    invalid?: boolean;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger id={id} className="w-full" aria-invalid={invalid}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {Object.entries(options).map(([key, text]) => (
                    <SelectItem key={key} value={key}>
                        {text}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
