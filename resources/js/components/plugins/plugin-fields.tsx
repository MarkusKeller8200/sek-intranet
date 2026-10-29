import FormField from '@/components/form-field';
import SelectField from '@/components/select-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { PluginField } from '@/types';

export type FieldValues = Record<string, string | number | boolean | null>;

/** Formularfelder aus der Beschreibung im Plugin-Manifest. */
export default function PluginFields({
    fields,
    values,
    onChange,
    secretsSet = [],
    prefix,
    onFile,
}: {
    fields: PluginField[];
    values: FieldValues;
    onChange: (key: string, value: string | number | boolean | null) => void;
    secretsSet?: string[];
    prefix: string;
    /** Nur für Aktionen mit Feld «file»: Datei bleibt im Browser, bis sie gesendet wird. */
    onFile?: (key: string, file: File | null) => void;
}) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            {fields.map((field) => {
                const id = `${prefix}-${field.key}`;
                const value = values[field.key];

                if (field.type === 'check') {
                    return (
                        <div
                            key={field.key}
                            className="flex items-start gap-2 md:col-span-2"
                        >
                            <Checkbox
                                id={id}
                                checked={value === true}
                                onCheckedChange={(
                                    checked: boolean | 'indeterminate',
                                ) => onChange(field.key, checked === true)}
                            />
                            <div className="grid gap-0.5">
                                <Label htmlFor={id}>{field.label}</Label>
                                {field.help && (
                                    <p className="text-xs text-muted-foreground">
                                        {field.help}
                                    </p>
                                )}
                            </div>
                        </div>
                    );
                }

                if (field.type === 'file') {
                    return (
                        <FormField
                            key={field.key}
                            id={id}
                            label={field.label + (field.required ? ' *' : '')}
                            hint={field.help}
                            className="md:col-span-2"
                        >
                            <Input
                                id={id}
                                type="file"
                                accept={field.accept}
                                onChange={(e) =>
                                    onFile?.(
                                        field.key,
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </FormField>
                    );
                }

                const isSecret = field.type === 'password';
                const hint =
                    isSecret && secretsSet.includes(field.key)
                        ? 'Gespeichert (verschlüsselt). Leer lassen = unverändert.'
                        : field.help;

                return (
                    <FormField
                        key={field.key}
                        id={id}
                        label={field.label + (field.required ? ' *' : '')}
                        hint={hint}
                        className={
                            field.type === 'textarea'
                                ? 'md:col-span-2'
                                : undefined
                        }
                    >
                        {field.type === 'select' ? (
                            <SelectField
                                id={id}
                                value={String(value ?? field.default ?? '')}
                                options={Object.fromEntries(
                                    field.choices ?? [],
                                )}
                                onChange={(v) => onChange(field.key, v)}
                            />
                        ) : field.type === 'textarea' ? (
                            <Textarea
                                id={id}
                                rows={4}
                                value={String(value ?? '')}
                                placeholder={field.placeholder}
                                onChange={(e) =>
                                    onChange(field.key, e.target.value)
                                }
                            />
                        ) : (
                            <Input
                                id={id}
                                type={
                                    isSecret
                                        ? 'password'
                                        : field.type === 'number'
                                          ? 'number'
                                          : field.type === 'url'
                                            ? 'url'
                                            : 'text'
                                }
                                autoComplete={isSecret ? 'new-password' : 'off'}
                                value={String(value ?? '')}
                                placeholder={field.placeholder}
                                onChange={(e) =>
                                    onChange(field.key, e.target.value)
                                }
                            />
                        )}
                    </FormField>
                );
            })}
        </div>
    );
}

export function initialValues(fields: PluginField[]): FieldValues {
    return Object.fromEntries(
        fields
            .filter((f) => f.type !== 'file')
            .map((f) => [
                f.key,
                f.default ?? (f.type === 'check' ? false : ''),
            ]),
    );
}
