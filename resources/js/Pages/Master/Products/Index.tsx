import React from 'react';
import AppLayout from '../../../Tailadmin/layout/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageBreadcrumb from '../../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../../Tailadmin/components/common/ComponentCard';
import Button from '../../../Tailadmin/components/ui/button/Button';
import Input from '../../../Tailadmin/components/form/input/InputField';
import SearchableSelect from '../../../Tailadmin/components/form/select/SearchableSelect';

export default function Index({ products, categories, suppliers, filters }: any) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this product?')) {
            router.delete(route('products.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Products" />
            <PageBreadcrumb pageTitle="Products" />

            <ComponentCard title="Product List">
                <div className="mb-4 flex gap-3 flex-wrap items-end">
                    <div className="min-w-[200px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
                        <Input
                            type="text"
                            defaultValue={filters?.search || ''}
                            placeholder="Part number or name..."
                            onChange={(e) => {
                                router.get(route('products.index'), {
                                    ...filters,
                                    search: e.target.value,
                                }, { preserveState: true, replace: true });
                            }}
                        />
                    </div>
                    <div className="min-w-[180px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Category</label>
                        <SearchableSelect
                            options={[
                                { value: '', label: 'All Categories' },
                                ...categories.map((c: any) => ({ value: c.id, label: c.name }))
                            ]}
                            value={filters?.category_id || ''}
                            onChange={(value) => {
                                router.get(route('products.index'), {
                                    ...filters,
                                    category_id: value as string,
                                }, { preserveState: true, replace: true });
                            }}
                        />
                    </div>
                    <div className="min-w-[180px]">
                        <label className="block text-xs font-medium text-gray-500 mb-1">Supplier</label>
                        <SearchableSelect
                            options={[
                                { value: '', label: 'All Suppliers' },
                                ...suppliers.map((s: any) => ({ value: s.id, label: s.name }))
                            ]}
                            value={filters?.supplier_id || ''}
                            onChange={(value) => {
                                router.get(route('products.index'), {
                                    ...filters,
                                    supplier_id: value as string,
                                }, { preserveState: true, replace: true });
                            }}
                        />
                    </div>
                </div>

                <div className="mb-3">
                    <Link href={route('products.create')}>
                        <Button>Add Product</Button>
                    </Link>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Part Number</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Model</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            {products.data.map((product: any) => (
                                <tr key={product.id}>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-mono">{product.part_number}</td>
                                    <td className="px-4 py-3 text-sm">{product.name}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{product.vehicle_model?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{product.supplier?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{product.category?.name || '-'}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {product.base_price ? `Rp ${Number(product.base_price).toLocaleString('id-ID')}` : '-'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        <Link href={route('products.show', product.id)} className="text-brand-500 hover:text-brand-700 mr-2">View</Link>
                                        <Link href={route('products.edit', product.id)} className="text-brand-500 hover:text-brand-700 mr-2">Edit</Link>
                                        <button onClick={() => handleDelete(product.id)} className="text-red-500 hover:text-red-700">Delete</button>
                                    </td>
                                </tr>
                            ))}
                            {products.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-sm text-gray-500">No products found.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {products.total > products.per_page && (
                    <div className="mt-4 flex justify-between items-center">
                        <div className="text-sm text-gray-500">
                            Showing {products.from || 0} to {products.to || 0} of {products.total}
                        </div>
                        <div className="flex gap-2">
                            {products.prev_page_url ? (
                                <Link href={products.prev_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Prev</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Prev</span>
                            )}
                            <span className="px-3 py-1 text-sm">Page {products.current_page} of {products.last_page}</span>
                            {products.next_page_url ? (
                                <Link href={products.next_page_url} className="px-3 py-1 text-sm border rounded hover:bg-gray-100 dark:hover:bg-gray-800">Next</Link>
                            ) : (
                                <span className="px-3 py-1 text-sm border rounded text-gray-400 cursor-not-allowed">Next</span>
                            )}
                        </div>
                    </div>
                )}
            </ComponentCard>
        </AppLayout>
    );
}
