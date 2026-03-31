import React, { useState, useEffect } from 'react';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost, apiDelete, apiPut } from '../services/api';
import { Plus, Search, MoreHorizontal, Building2, Mail, Phone, Edit2, Trash2, Eye, ExternalLink } from 'lucide-react';

interface Contact {
    id: number;
    name: string;
    email: string;
    phone: string;
    role: string;
    is_primary: boolean;
}

interface Client {
    id: number;
    name: string;
    company: string;
    email: string;
    phone: string;
    website: string;
    status: string;
    address: string;
    city: string;
    state: string;
    country: string;
    postal_code: string;
    vat_number: string;
    notes: string;
    contacts: Contact[];
    project_count: number;
    created_at: string;
    updated_at: string;
}

export default function Clients() {
    const [clients, setClients] = useState<Client[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [showViewModal, setShowViewModal] = useState(false);
    const [selectedClient, setSelectedClient] = useState<Client | null>(null);
    const [newClient, setNewClient] = useState({
        name: '', company: '', email: '', phone: '', website: '',
        address: '', city: '', state: '', country: '', postal_code: '',
        vat_number: '', notes: '', status: 'active'
    });
    const [saving, setSaving] = useState(false);
    const [menuOpen, setMenuOpen] = useState<number | null>(null);

    useEffect(() => {
        loadClients();
    }, [search, statusFilter]);

    async function loadClients() {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            const res = await apiGet<Client[]>('/aosai/v1/clients', params);
            setClients(Array.isArray(res) ? res : []);
        } catch {
            setClients([]);
        } finally {
            setLoading(false);
        }
    }

    async function handleCreate(e: React.FormEvent) {
        e.preventDefault();
        if (!newClient.name.trim()) return;
        setSaving(true);
        try {
            const res = await apiPost<Client>('/aosai/v1/clients', newClient);
            setClients(prev => [res, ...prev]);
            setShowModal(false);
            resetForm();
        } catch (err: any) {
            alert(err.message);
        } finally {
            setSaving(false);
        }
    }

    async function handleUpdate(e: React.FormEvent) {
        e.preventDefault();
        if (!selectedClient || !newClient.name.trim()) return;
        setSaving(true);
        try {
            const res = await apiPut<Client>(`/aosai/v1/clients/${selectedClient.id}`, newClient);
            setClients(prev => prev.map(c => c.id === res.id ? res : c));
            setShowViewModal(false);
            setSelectedClient(null);
            resetForm();
        } catch (err: any) {
            alert(err.message);
        } finally {
            setSaving(false);
        }
    }

    async function handleDelete(id: number) {
        if (!confirm('Delete this client? This cannot be undone.')) return;
        try {
            await apiDelete(`/aosai/v1/clients/${id}`);
            setClients(prev => prev.filter(c => c.id !== id));
        } catch (err: any) {
            alert(err.message);
        }
        setMenuOpen(null);
    }

    function resetForm() {
        setNewClient({
            name: '', company: '', email: '', phone: '', website: '',
            address: '', city: '', state: '', country: '', postal_code: '',
            vat_number: '', notes: '', status: 'active'
        });
    }

    function openEdit(client: Client) {
        setSelectedClient(client);
        setNewClient({
            name: client.name,
            company: client.company || '',
            email: client.email || '',
            phone: client.phone || '',
            website: client.website || '',
            address: client.address || '',
            city: client.city || '',
            state: client.state || '',
            country: client.country || '',
            postal_code: client.postal_code || '',
            vat_number: client.vat_number || '',
            notes: client.notes || '',
            status: client.status || 'active'
        });
        setShowViewModal(true);
    }

    const statusColors: Record<string, string> = {
        active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        inactive: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
        lead: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    };

    return (
        <DashboardLayout>
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Clients</h1>
                        <p className="text-sm text-gray-500 mt-1">Manage your clients and contacts</p>
                    </div>
                    <button
                        onClick={() => { resetForm(); setShowModal(true); }}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium"
                    >
                        <Plus className="w-4 h-4" /> Add Client
                    </button>
                </div>

                <div className="flex items-center gap-3">
                    <div className="relative flex-1 max-w-md">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search clients..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full pl-9 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                        />
                    </div>
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="lead">Lead</option>
                    </select>
                </div>

                {loading ? (
                    <LoadingSpinner />
                ) : clients.length === 0 ? (
                    <div className="text-center py-16">
                        <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <Building2 className="w-8 h-8 text-gray-400" />
                        </div>
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-1">No clients found</h3>
                        <p className="text-sm text-gray-500 mb-4">Add your first client to get started</p>
                        <button onClick={() => setShowModal(true)} className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm">
                            <Plus className="w-4 h-4" /> Add Client
                        </button>
                    </div>
                ) : (
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <table className="w-full">
                            <thead className="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contact</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Projects</th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {clients.map((client) => (
                                    <tr key={client.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                                                    <span className="text-sm font-medium text-primary-600 dark:text-primary-400">
                                                        {client.name.charAt(0).toUpperCase()}
                                                    </span>
                                                </div>
                                                <span className="font-medium text-gray-900 dark:text-white">{client.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{client.company || '-'}</td>
                                        <td className="px-6 py-4">
                                            <div className="text-sm">
                                                <div className="flex items-center gap-1 text-gray-900 dark:text-white">
                                                    <Mail className="w-3 h-3" /> {client.email || '-'}
                                                </div>
                                                {client.phone && (
                                                    <div className="flex items-center gap-1 text-gray-500 mt-1">
                                                        <Phone className="w-3 h-3" /> {client.phone}
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`text-xs px-2 py-1 rounded-full font-medium ${statusColors[client.status] || statusColors.active}`}>
                                                {client.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{client.project_count || 0}</td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="relative">
                                                <button
                                                    onClick={() => setMenuOpen(menuOpen === client.id ? null : client.id)}
                                                    className="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400"
                                                >
                                                    <MoreHorizontal className="w-4 h-4" />
                                                </button>
                                                {menuOpen === client.id && (
                                                    <div className="absolute right-0 mt-1 w-36 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-10 py-1">
                                                        <button onClick={() => { openEdit(client); setMenuOpen(null); }} className="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                                                            <Eye className="w-3 h-3" /> View/Edit
                                                        </button>
                                                        <button onClick={() => { window.open(`/client-portal/${client.id}`, '_blank'); setMenuOpen(null); }} className="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                                                            <ExternalLink className="w-3 h-3" /> Portal
                                                        </button>
                                                        <button onClick={() => handleDelete(client.id)} className="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2">
                                                            <Trash2 className="w-3 h-3" /> Delete
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowModal(false)}>
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                        <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Add New Client</h2>
                        </div>
                        <form onSubmit={handleCreate} className="p-6 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Client Name *</label>
                                    <input
                                        type="text"
                                        required
                                        value={newClient.name}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, name: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                        placeholder="John Doe"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company</label>
                                    <input
                                        type="text"
                                        value={newClient.company}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, company: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                        placeholder="Acme Inc."
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                    <input
                                        type="email"
                                        value={newClient.email}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, email: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                        placeholder="john@example.com"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                                    <input
                                        type="tel"
                                        value={newClient.phone}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, phone: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                        placeholder="+1 234 567 890"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Website</label>
                                <input
                                    type="url"
                                    value={newClient.website}
                                    onChange={(e) => setNewClient(prev => ({ ...prev, website: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    placeholder="https://example.com"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                                <input
                                    type="text"
                                    value={newClient.address}
                                    onChange={(e) => setNewClient(prev => ({ ...prev, address: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    placeholder="123 Main St"
                                />
                            </div>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                                    <input
                                        type="text"
                                        value={newClient.city}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, city: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">State</label>
                                    <input
                                        type="text"
                                        value={newClient.state}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, state: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Postal Code</label>
                                    <input
                                        type="text"
                                        value={newClient.postal_code}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, postal_code: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Country</label>
                                    <input
                                        type="text"
                                        value={newClient.country}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, country: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">VAT Number</label>
                                    <input
                                        type="text"
                                        value={newClient.vat_number}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, vat_number: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                                <textarea
                                    value={newClient.notes}
                                    onChange={(e) => setNewClient(prev => ({ ...prev, notes: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 resize-none"
                                    rows={3}
                                />
                            </div>
                            <div className="flex items-center justify-end gap-3 pt-2">
                                <button type="button" onClick={() => setShowModal(false)} className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Cancel</button>
                                <button type="submit" disabled={saving} className="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
                                    {saving ? 'Saving...' : 'Save Client'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {showViewModal && selectedClient && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowViewModal(false)}>
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                        <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Edit Client</h2>
                            <span className={`text-xs px-2 py-1 rounded-full font-medium ${statusColors[newClient.status] || statusColors.active}`}>
                                {newClient.status}
                            </span>
                        </div>
                        <form onSubmit={handleUpdate} className="p-6 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Client Name *</label>
                                    <input
                                        type="text"
                                        required
                                        value={newClient.name}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, name: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company</label>
                                    <input
                                        type="text"
                                        value={newClient.company}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, company: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                    <input
                                        type="email"
                                        value={newClient.email}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, email: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                                    <input
                                        type="tel"
                                        value={newClient.phone}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, phone: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Website</label>
                                <input
                                    type="url"
                                    value={newClient.website}
                                    onChange={(e) => setNewClient(prev => ({ ...prev, website: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                                <input
                                    type="text"
                                    value={newClient.address}
                                    onChange={(e) => setNewClient(prev => ({ ...prev, address: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                />
                            </div>
                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                                    <input
                                        type="text"
                                        value={newClient.city}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, city: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">State</label>
                                    <input
                                        type="text"
                                        value={newClient.state}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, state: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Postal Code</label>
                                    <input
                                        type="text"
                                        value={newClient.postal_code}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, postal_code: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Country</label>
                                    <input
                                        type="text"
                                        value={newClient.country}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, country: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">VAT Number</label>
                                    <input
                                        type="text"
                                        value={newClient.vat_number}
                                        onChange={(e) => setNewClient(prev => ({ ...prev, vat_number: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                <select
                                    value={newClient.status}
                                    onChange={(e) => setNewClient(prev => ({ ...prev, status: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="lead">Lead</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                                <textarea
                                    value={newClient.notes}
                                    onChange={(e) => setNewClient(prev => ({ ...prev, notes: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 resize-none"
                                    rows={3}
                                />
                            </div>
                            <div className="flex items-center justify-end gap-3 pt-2">
                                <button type="button" onClick={() => setShowViewModal(false)} className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Cancel</button>
                                <button type="submit" disabled={saving} className="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
                                    {saving ? 'Saving...' : 'Update Client'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </DashboardLayout>
    );
}
