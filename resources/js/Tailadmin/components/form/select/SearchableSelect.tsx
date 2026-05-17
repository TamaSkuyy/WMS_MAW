import React, { useState } from 'react'
import { Combobox, ComboboxInput, ComboboxOptions, ComboboxOption, ComboboxButton } from '@headlessui/react'

interface Option {
    value: string | number;
    label: string;
}

interface SearchableSelectProps {
    options: Option[];
    value: string | number;
    onChange: (value: string | number) => void;
    placeholder?: string;
    className?: string;
}

export default function SearchableSelect({ options, value, onChange, placeholder = 'Select an option...', className = '' }: SearchableSelectProps) {
    const [query, setQuery] = useState('')
    const buttonRef = React.useRef<HTMLButtonElement>(null)

    const filteredOptions =
        query === ''
            ? options
            : options.filter((option) =>
                option.label.toLowerCase().indexOf(query.toLowerCase()) !== -1
            )

    const selectedOption = options.find((opt) => opt.value === value) || null;

    return (
        <div className={`relative ${className}`}>
            <Combobox value={selectedOption} onChange={(opt: Option | null) => onChange(opt ? opt.value : '')}>
                {({ open }) => (
                    <>
                        <div className="relative w-full cursor-default overflow-hidden rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-300 dark:focus-within:border-brand-800 text-left text-sm h-11 transition-all">
                            <ComboboxInput
                                className="w-full h-full border-none py-2.5 pl-4 pr-10 text-sm leading-5 text-gray-800 dark:text-white/90 bg-transparent focus:ring-0 focus:outline-none placeholder:text-gray-400 dark:placeholder:text-white/30"
                                displayValue={(option: Option | null) => option ? option.label : ''}
                                onChange={(event) => setQuery(event.target.value)}
                                onClick={(e) => {
                                    (e.target as HTMLInputElement).select();
                                    if (!open) {
                                        buttonRef.current?.click();
                                    }
                                }}
                                placeholder={placeholder}
                                autoComplete="off"
                            />
                            <ComboboxButton ref={buttonRef} className="absolute inset-y-0 right-0 flex items-center pr-2">
                                <svg className="h-5 w-5 text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                                    <path d="M7 7l3-3 3 3m0 6l-3 3-3-3" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                </svg>
                            </ComboboxButton>
                        </div>
                
                <ComboboxOptions 
                    transition
                    className="absolute mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white dark:bg-gray-dark py-2 text-sm shadow-theme-lg ring-1 ring-black/5 dark:ring-white/5 focus:outline-none z-50 empty:hidden data-[leave]:transition data-[leave]:duration-100 data-[leave]:ease-in data-[closed]:opacity-0 custom-scrollbar"
                >
                    {filteredOptions.length === 0 && query !== '' ? (
                        <div className="relative cursor-default select-none py-2 px-4 text-gray-500 dark:text-gray-400">
                            Nothing found.
                        </div>
                    ) : (
                        filteredOptions.map((option) => (
                            <ComboboxOption
                                key={option.value}
                                value={option}
                                className="group relative cursor-pointer select-none py-2.5 pl-4 pr-9 text-gray-700 dark:text-gray-300 data-[focus]:bg-brand-50 dark:data-[focus]:bg-brand-500/10 data-[focus]:text-brand-500 dark:data-[focus]:text-brand-400"
                            >
                                <span className="block truncate group-data-[selected]:font-medium font-normal">
                                    {option.label}
                                </span>

                                <span className="absolute inset-y-0 right-0 flex items-center pr-4 text-brand-500 dark:text-brand-400 hidden group-data-[selected]:flex">
                                    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clipRule="evenodd" />
                                    </svg>
                                </span>
                            </ComboboxOption>
                        ))
                    )}
                </ComboboxOptions>
                    </>
                )}
            </Combobox>
        </div>
    )
}
