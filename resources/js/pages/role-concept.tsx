import { Head } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';
import OptionBadge from '@/components/option-badge';
import PageHeader from '@/components/page-header';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { isColorName, palette } from '@/lib/colors';

type Role = {
    key: string;
    label: string;
    description: string;
    people: number;
    abilities: string[];
    menus: { key: string; title: string; items: number }[];
};

type Ability = { key: string; label: string };
type Menu = { key: string; title: string; color: string };

const principles = [
    'Menüs und Seiten fragen Rechte ab (z. B. «use-ict»), nie Rollen. Neue Rolle = nur Zuordnung in config/intranet.php → abilities ändern.',
    'Im Prototyp hat jede Person eine Rolle (Feld «Rolle im Intranet»). Die Rolle «Fachstelle ICT» enthält auch die Rechte einer Lehrperson.',
    'Später kommen die Rollen aus Gruppen in Entra ID (Anmeldung über OIDC). Eine Person kann dann mehrere Rollen haben; ihre Rechte werden zusammengezählt.',
    'So wenig Administration wie möglich: Personen und Plugins verwaltet nur die Administration.',
    'Personendaten bleiben nach Feldgruppen geschützt: private Angaben und Systemfelder sieht nur die Administration – unabhängig von der Rolle.',
];

function Yes({ on }: { on: boolean }) {
    return on ? (
        <Check className="mx-auto size-4 text-emerald-600" aria-label="ja" />
    ) : (
        <Minus
            className="mx-auto size-4 text-muted-foreground/40"
            aria-label="nein"
        />
    );
}

export default function RoleConcept({
    roles,
    abilities,
    allMenus,
}: {
    roles: Role[];
    abilities: Ability[];
    allMenus: Menu[];
}) {
    return (
        <>
            <Head title="Rollenkonzept" />
            <div className="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Rollenkonzept"
                    description="Welche Rolle welche Rechte hat und welche Menüs sie sieht. Quelle: config/intranet.php → roles und abilities."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Grundsätze</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="grid list-decimal gap-2 pl-5 text-sm">
                            {principles.map((p) => (
                                <li key={p}>{p}</li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Rollen</CardTitle>
                        <CardDescription>
                            Mit der Anzahl aktiver Personen je Rolle.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-2">
                        {roles.map((role) => (
                            <div
                                key={role.key}
                                className="grid gap-1 rounded-lg border p-3 text-sm"
                            >
                                <div className="flex items-center gap-2">
                                    <OptionBadge
                                        list="roles"
                                        value={role.key}
                                    />
                                    <span className="ml-auto text-xs text-muted-foreground">
                                        {`${role.people} Personen`}
                                    </span>
                                </div>
                                <p className="text-muted-foreground">
                                    {role.description}
                                </p>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Rechte je Rolle</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">
                                        Recht
                                    </th>
                                    {roles.map((role) => (
                                        <th
                                            key={role.key}
                                            className="px-2 py-2 text-center font-medium"
                                        >
                                            {role.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {abilities.map((ability) => (
                                    <tr
                                        key={ability.key}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-2 pr-4">
                                            <div>{ability.label}</div>
                                            <code
                                                className="text-xs text-muted-foreground"
                                                translate="no"
                                            >
                                                {ability.key}
                                            </code>
                                        </td>
                                        {roles.map((role) => (
                                            <td
                                                key={role.key}
                                                className="px-2 py-2"
                                            >
                                                <Yes
                                                    on={role.abilities.includes(
                                                        ability.key,
                                                    )}
                                                />
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Sichtbare Menüs je Rolle</CardTitle>
                        <CardDescription>
                            Berechnet aus der Navigation – so sieht eine Person
                            mit dieser Rolle die Seitenleiste.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">
                                        Menü
                                    </th>
                                    {roles.map((role) => (
                                        <th
                                            key={role.key}
                                            className="px-2 py-2 text-center font-medium"
                                        >
                                            {role.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {allMenus.map((menu) => {
                                    const color = isColorName(menu.color)
                                        ? palette[menu.color]
                                        : palette.slate;

                                    return (
                                        <tr
                                            key={menu.key}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2 pr-4">
                                                <span className="flex items-center gap-2">
                                                    <span
                                                        className={`size-2.5 rounded-full ${color.dot}`}
                                                    />
                                                    {menu.title}
                                                </span>
                                            </td>
                                            {roles.map((role) => (
                                                <td
                                                    key={role.key}
                                                    className="px-2 py-2"
                                                >
                                                    <Yes
                                                        on={role.menus.some(
                                                            (m) =>
                                                                m.key ===
                                                                menu.key,
                                                        )}
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

RoleConcept.layout = {
    breadcrumbs: [{ title: 'Rollenkonzept', href: '/rollenkonzept' }],
};
