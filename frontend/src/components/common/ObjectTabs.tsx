interface ObjectTab<Key extends string> {
  key: Key;
  label: string;
}

interface ObjectTabsProps<Key extends string> {
  tabs: ObjectTab<Key>[];
  active: Key;
  onChange: (tab: Key) => void;
}

export default function ObjectTabs<Key extends string>({ tabs, active, onChange }: ObjectTabsProps<Key>) {
  return <div className="flex w-full items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900" role="tablist" aria-label="Sezioni del dettaglio">
    {tabs.map((tab) => <button key={tab.key} type="button" role="tab" aria-selected={active === tab.key} onClick={() => onChange(tab.key)} className={`w-full rounded-md px-3 py-2 text-sm font-medium transition hover:text-gray-900 dark:hover:text-white ${active === tab.key ? "bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white" : "text-gray-500 dark:text-gray-400"}`}>{tab.label}</button>)}
  </div>;
}
