import React, { useState } from 'react';
import AppLayout from '../../Tailadmin/layout/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import PageBreadcrumb from '../../Tailadmin/components/common/PageBreadCrumb';
import ComponentCard from '../../Tailadmin/components/common/ComponentCard';
import Button from '../../Tailadmin/components/ui/button/Button';
import Input from '../../Tailadmin/components/form/input/InputField';
import Label from '../../Tailadmin/components/form/Label';
import SearchableSelect from '../../Tailadmin/components/form/select/SearchableSelect';

export default function Index({ menuItems, permissions, parentMenus }: any) {
    const [isEditing, setIsEditing] = useState(false);
    const [editId, setEditId] = useState<number | null>(null);

    const { data, setData, post, put, delete: destroy, reset, errors } = useForm({
        name: '',
        icon: '',
        path: '',
        parent_id: '',
        sort_order: 0,
        permission_name: '',
        group: 'main',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing && editId) {
            put(route('menus.update', editId), {
                onSuccess: () => {
                    reset();
                    setIsEditing(false);
                    setEditId(null);
                },
            });
        } else {
            post(route('menus.store'), {
                onSuccess: () => reset(),
            });
        }
    };

    const handleEdit = (menu: any) => {
        setIsEditing(true);
        setEditId(menu.id);
        setData({
            name: menu.name,
            icon: menu.icon || '',
            path: menu.path || '',
            parent_id: menu.parent_id || '',
            sort_order: menu.sort_order,
            permission_name: menu.permission_name || '',
            group: menu.group,
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this menu?')) {
            destroy(route('menus.destroy', id));
        }
    };

    const cancelEdit = () => {
        setIsEditing(false);
        setEditId(null);
        reset();
    };

    return (
        <AppLayout>
            <Head title="Menu Management" />
            <PageBreadcrumb pageTitle="Menu Management" />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div className="xl:col-span-1">
                    <ComponentCard title={isEditing ? 'Edit Menu' : 'Add New Menu'}>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <Label>Name</Label>
                                <Input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Menu Name"
                                />
                                {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                            </div>

                            <div>
                                <Label>Path (URL)</Label>
                                <Input
                                    type="text"
                                    value={data.path}
                                    onChange={(e) => setData('path', e.target.value)}
                                    placeholder="/dashboard"
                                />
                            </div>

                            <div>
                                <Label>Icon Component Name</Label>
                                <Input
                                    type="text"
                                    value={data.icon}
                                    onChange={(e) => setData('icon', e.target.value)}
                                    placeholder="GridIcon"
                                />
                            </div>

                            <div>
                                <Label>Group</Label>
                                <SearchableSelect
                                    options={[
                                        { value: 'main', label: 'Main Menu' },
                                        { value: 'others', label: 'Others' }
                                    ]}
                                    value={data.group}
                                    onChange={(value) => setData('group', value as string)}
                                />
                            </div>

                            <div>
                                <Label>Parent Menu</Label>
                                <SearchableSelect
                                    options={[
                                        { value: '', label: '-- No Parent --' },
                                        ...parentMenus.map((pm: any) => ({ value: pm.id, label: pm.name }))
                                    ]}
                                    value={data.parent_id}
                                    onChange={(value) => setData('parent_id', value as string)}
                                />
                            </div>

                            <div>
                                <Label>Required Permission</Label>
                                <SearchableSelect
                                    options={[
                                        { value: '', label: '-- No Permission Required --' },
                                        ...permissions.map((p: any) => ({ value: p.name, label: p.name }))
                                    ]}
                                    value={data.permission_name}
                                    onChange={(value) => setData('permission_name', value as string)}
                                />
                            </div>

                            <div>
                                <Label>Sort Order</Label>
                                <Input
                                    type="number"
                                    value={data.sort_order}
                                    onChange={(e) => setData('sort_order', parseInt(e.target.value))}
                                />
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" className="w-full">
                                    {isEditing ? 'Update Menu' : 'Save Menu'}
                                </Button>
                                {isEditing && (
                                    <Button type="button" variant="outline" onClick={cancelEdit} className="w-full">
                                        Cancel
                                    </Button>
                                )}
                            </div>
                        </form>
                    </ComponentCard>
                </div>

                <div className="xl:col-span-2">
                    <ComponentCard title="Menu List">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Path</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                                    {menuItems.map((menu: any) => (
                                        <tr key={menu.id}>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm">{menu.name}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{menu.path || '-'}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{menu.group}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{menu.parent?.name || '-'}</td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                                <button onClick={() => handleEdit(menu)} className="text-brand-500 hover:text-brand-700 mr-3">Edit</button>
                                                <button onClick={() => handleDelete(menu.id)} className="text-red-500 hover:text-red-700">Delete</button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </ComponentCard>
                </div>
            </div>
        </AppLayout>
    );
}
