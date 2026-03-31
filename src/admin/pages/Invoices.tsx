import React, { useState, useEffect } from 'react';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost, apiDelete, apiPut } from '../services/api';
import { Plus, Search, MoreHorizontal, FileText, Send, Download, Trash2, Eye, CheckCircle, Clock, XCircle } from 'lucide-react';

interface LineItem {
    id?: number;
    description: string;
    quantity: number;
    rate: number;
    amount: number;
}

interface Invoice {
    id: number;
    invoice_number: string;
    client_id: number;
    client_name: string;
    status: string;
    subtotal: number;
    tax_rate: number;
    tax_amount: number;
    total: number;
    currency: string;
    issue_date: string;
    due_date: string;
    paid_date?: string;
    items: LineItem[];
    notes: string;
    created_at: string;
}

interface Client {
    id: number;
    name: string;
    company: string;
}

export default function Invoices() {
    const [invoices, setInvoices] = useState<Invoice[]>([]);
    const [clients, setClients] = useState<Client[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [showViewModal, setShowViewModal] = useState(false);
    const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null);
    const [newInvoice, setNewInvoice] = useState({
        client_id: '',
        issue_date: new Date().toISOString().split('T')[0],
        due_date: '',
        tax_rate: 0,
        notes: '',
        items: [{ description: '', quantity: 1, rate: 0, amount: 0 }]
    });
    const [saving, setSaving] = useState(false);
    const [menuOpen, setMenuOpen] = useState<number | null>(null);

    useEffect(() => {
        loadInvoices();
        loadClients();
    }, [search, statusFilter]);

    async function loadInvoices() {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            const res = await apiGet<Invoice[]>('/aosai/v1/invoices', params);
            setInvoices(Array.isArray(res) ? res : []);
        } catch {
            setInvoices([]);
        } finally {
            setLoading(false);
        }
    }

    async function loadClients() {
        try {
            const res = await apiGet<Client[]>('/aosai/v1/clients', { status: 'active' });
            setClients(Array.isArray(res) ? res : []);
        } catch {
            setClients([]);
        }
    }

    function calculateTotals() {
        const subtotal = newInvoice.items.reduce((sum, item) => sum + (item.quantity * item.rate), 0);
        const taxAmount = subtotal * (newInvoice.tax_rate / 100);
        const total = subtotal + taxAmount;
        return { subtotal, taxAmount, total };
    }

    function updateItem(index: number, field: keyof LineItem, value: string | number) {
        const items = [...newInvoice.items];
        items[index] = { ...items[index], [field]: value };
        if (field === 'quantity' || field === 'rate') {
            items[index].amount = items[index].quantity * items[index].rate;
        }
        setNewInvoice(prev => ({ ...prev, items }));
    }

    function addItem() {
        setNewInvoice(prev => ({
            ...prev,
            items: [...prev.items, { description: '', quantity: 1, rate: 0, amount: 0 }]
        }));
    }

    function removeItem(index: number) {
        const items = newInvoice.items.filter((_, i) => i !== index);
        setNewInvoice(prev => ({ ...prev, items }));
    }

    async function handleCreate(e: React.FormEvent) {
        e.preventDefault();
        if (!newInvoice.client_id) {
            alert('Please select a client');
            return;
        }
        setSaving(true);
        try {
            const payload = {
                ...newInvoice,
                client_id: parseInt(newInvoice.client_id),
                tax_rate: parseFloat(String(newInvoice.tax_rate)) || 0
            };
            const res = await apiPost<Invoice>('/aosai/v1/invoices', payload);
            setInvoices(prev => [res, ...prev]);
            setShowModal(false);
            resetForm();
        } catch (err: any) {
            alert(err.message);
        } finally {
            setSaving(false);
        }
    }

    async function handleSend(invoice: Invoice) {
        if (!confirm(`Send invoice ${invoice.invoice_number} to ${invoice.client_name}?`)) return;
        try {
            await apiPost(`/aosai/v1/invoices/${invoice.id}/send`, {});
            loadInvoices();
        } catch (err: any) {
            alert(err.message);
        }
        setMenuOpen(null);
    }

    async function handleMarkPaid(invoice: Invoice) {
        try {
            const res = await apiPut<Invoice>(`/aosai/v1/invoices/${invoice.id}`, { status: 'paid' });
            setInvoices(prev => prev.map(i => i.id === res.id ? res : i));
        } catch (err: any) {
            alert(err.message);
        }
        setMenuOpen(null);
    }

    async function handleDelete(id: number) {
        if (!confirm('Delete this invoice? This cannot be undone.')) return;
        try {
            await apiDelete(`/aosai/v1/invoices/${id}`);
            setInvoices(prev => prev.filter(i => i.id !== id));
        } catch (err: any) {
            alert(err.message);
        }
        setMenuOpen(null);
    }

    function resetForm() {
        setNewInvoice({
            client_id: '',
            issue_date: new Date().toISOString().split('T')[0],
            due_date: '',
            tax_rate: 0,
            notes: '',
            items: [{ description: '', quantity: 1, rate: 0, amount: 0 }]
        });
    }

    function formatCurrency(amount: number, currency = 'USD') {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount);
    }

    const statusColors: Record<string, { bg: string; text: string; icon: React.ReactNode }> = {
        draft: { bg: 'bg-gray-100 dark:bg-gray-700', text: 'text-gray-600 dark:text-gray-400', icon: <FileText className="w-4 h-4" /> },
        sent: { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-700 dark:text-blue-400', icon: <Send className="w-4 h-4" /> },
        paid: { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-700 dark:text-green-400', icon: <CheckCircle className="w-4 h-4" /> },
        overdue: { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-700 dark:text-red-400', icon: <XCircle className="w-4 h-4" /> },
        cancelled: { bg: 'bg-gray-100 dark:bg-gray-700', text: 'text-gray-500 dark:text-gray-500', icon: <Clock className="w-4 h-4" /> },
    };

    const { subtotal, taxAmount, total } = calculateTotals();

    return (
        <DashboardLayout>
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Invoices</h1>
                        <p className="text-sm text-gray-500 mt-1">Create and manage client invoices</p>
                    </div>
                    <button
                        onClick={() => { resetForm(); setShowModal(true); }}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium"
                    >
                        <Plus className="w-4 h-4" /> Create Invoice
                    </button>
                </div>

                <div className="flex items-center gap-3">
                    <div className="relative flex-1 max-w-md">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search invoices..."
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
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="paid">Paid</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>

                {loading ? (
                    <LoadingSpinner />
                ) : invoices.length === 0 ? (
                    <div className="text-center py-16">
                        <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <FileText className="w-8 h-8 text-gray-400" />
                        </div>
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-1">No invoices found</h3>
                        <p className="text-sm text-gray-500 mb-4">Create your first invoice to get started</p>
                        <button onClick={() => setShowModal(true)} className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm">
                            <Plus className="w-4 h-4" /> Create Invoice
                        </button>
                    </div>
                ) : (
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <table className="w-full">
                            <thead className="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invoice</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Due Date</th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {invoices.map((invoice) => (
                                    <tr key={invoice.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td className="px-6 py-4">
                                            <span className="font-medium text-gray-900 dark:text-white">{invoice.invoice_number}</span>
                                            <div className="text-xs text-gray-500 mt-1">
                                                Issued: {new Date(invoice.issue_date).toLocaleDateString()}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-900 dark:text-white">{invoice.client_name}</td>
                                        <td className="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(invoice.total, invoice.currency)}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full font-medium ${statusColors[invoice.status]?.bg} ${statusColors[invoice.status]?.text}`}>
                                                {statusColors[invoice.status]?.icon}
                                                {invoice.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {new Date(invoice.due_date).toLocaleDateString()}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="relative">
                                                <button
                                                    onClick={() => setMenuOpen(menuOpen === invoice.id ? null : invoice.id)}
                                                    className="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400"
                                                >
                                                    <MoreHorizontal className="w-4 h-4" />
                                                </button>
                                                {menuOpen === invoice.id && (
                                                    <div className="absolute right-0 mt-1 w-40 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-10 py-1">
                                                        <button onClick={() => { setSelectedInvoice(invoice); setShowViewModal(true); setMenuOpen(null); }} className="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                                                            <Eye className="w-3 h-3" /> View
                                                        </button>
                                                        <button onClick={() => handleSend(invoice)} className="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                                                            <Send className="w-3 h-3" /> Send
                                                        </button>
                                                        <button onClick={() => handleMarkPaid(invoice)} className="w-full px-3 py-2 text-left text-sm text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 flex items-center gap-2">
                                                            <CheckCircle className="w-3 h-3" /> Mark Paid
                                                        </button>
                                                        <button onClick={() => { window.open(`/api/aosai/v1/invoices/${invoice.id}/pdf`, '_blank'); setMenuOpen(null); }} className="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                                                            <Download className="w-3 h-3" /> Download PDF
                                                        </button>
                                                        <button onClick={() => handleDelete(invoice.id)} className="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2">
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
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                        <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Create New Invoice</h2>
                        </div>
                        <form onSubmit={handleCreate} className="p-6 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Client *</label>
                                    <select
                                        required
                                        value={newInvoice.client_id}
                                        onChange={(e) => setNewInvoice(prev => ({ ...prev, client_id: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    >
                                        <option value="">Select a client</option>
                                        {clients.map(c => (
                                            <option key={c.id} value={c.id}>{c.name}{c.company ? ` (${c.company})` : ''}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Issue Date</label>
                                    <input
                                        type="date"
                                        value={newInvoice.issue_date}
                                        onChange={(e) => setNewInvoice(prev => ({ ...prev, issue_date: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Due Date</label>
                                    <input
                                        type="date"
                                        required
                                        value={newInvoice.due_date}
                                        onChange={(e) => setNewInvoice(prev => ({ ...prev, due_date: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tax Rate (%)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={newInvoice.tax_rate}
                                        onChange={(e) => setNewInvoice(prev => ({ ...prev, tax_rate: parseFloat(e.target.value) || 0 }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                            </div>

                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Line Items</label>
                                    <button type="button" onClick={addItem} className="text-sm text-primary-600 hover:text-primary-700">
                                        + Add Item
                                    </button>
                                </div>
                                <div className="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <table className="w-full text-sm">
                                        <thead className="bg-gray-50 dark:bg-gray-900/50">
                                            <tr>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">Description</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 w-24">Qty</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 w-32">Rate</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 w-32">Amount</th>
                                                <th className="w-12"></th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                            {newInvoice.items.map((item, index) => (
                                                <tr key={index}>
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="text"
                                                            value={item.description}
                                                            onChange={(e) => updateItem(index, 'description', e.target.value)}
                                                            placeholder="Service description"
                                                            className="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="number"
                                                            value={item.quantity}
                                                            onChange={(e) => updateItem(index, 'quantity', parseFloat(e.target.value) || 0)}
                                                            min="0"
                                                            step="0.01"
                                                            className="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="number"
                                                            value={item.rate}
                                                            onChange={(e) => updateItem(index, 'rate', parseFloat(e.target.value) || 0)}
                                                            min="0"
                                                            step="0.01"
                                                            className="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-medium text-gray-900 dark:text-white">
                                                        {formatCurrency(item.amount)}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {newInvoice.items.length > 1 && (
                                                            <button type="button" onClick={() => removeItem(index)} className="text-red-500 hover:text-red-700">
                                                                <Trash2 className="w-4 h-4" />
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <div className="w-64 space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Subtotal:</span>
                                        <span className="font-medium text-gray-900 dark:text-white">{formatCurrency(subtotal)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Tax ({newInvoice.tax_rate}%):</span>
                                        <span className="font-medium text-gray-900 dark:text-white">{formatCurrency(taxAmount)}</span>
                                    </div>
                                    <div className="flex justify-between text-lg font-semibold border-t border-gray-200 dark:border-gray-700 pt-2">
                                        <span className="text-gray-900 dark:text-white">Total:</span>
                                        <span className="text-primary-600">{formatCurrency(total)}</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                                <textarea
                                    value={newInvoice.notes}
                                    onChange={(e) => setNewInvoice(prev => ({ ...prev, notes: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 resize-none"
                                    rows={2}
                                    placeholder="Additional notes or payment terms"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-3 pt-2">
                                <button type="button" onClick={() => setShowModal(false)} className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Cancel</button>
                                <button type="submit" disabled={saving} className="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
                                    {saving ? 'Creating...' : 'Create Invoice'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {showViewModal && selectedInvoice && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowViewModal(false)}>
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                        <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Invoice {selectedInvoice.invoice_number}</h2>
                            <span className={`inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full font-medium ${statusColors[selectedInvoice.status]?.bg} ${statusColors[selectedInvoice.status]?.text}`}>
                                {statusColors[selectedInvoice.status]?.icon}
                                {selectedInvoice.status}
                            </span>
                        </div>
                        <div className="p-6">
                            <div className="grid grid-cols-2 gap-6 mb-6">
                                <div>
                                    <h3 className="text-xs font-medium text-gray-500 uppercase mb-1">Bill To</h3>
                                    <p className="font-medium text-gray-900 dark:text-white">{selectedInvoice.client_name}</p>
                                </div>
                                <div className="text-right">
                                    <h3 className="text-xs font-medium text-gray-500 uppercase mb-1">Invoice Details</h3>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Issued: {new Date(selectedInvoice.issue_date).toLocaleDateString()}</p>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Due: {new Date(selectedInvoice.due_date).toLocaleDateString()}</p>
                                </div>
                            </div>

                            <table className="w-full mb-6 text-sm">
                                <thead className="bg-gray-50 dark:bg-gray-900/50">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500">Description</th>
                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500">Qty</th>
                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500">Rate</th>
                                        <th className="px-4 py-2 text-right text-xs font-medium text-gray-500">Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {selectedInvoice.items?.map((item, index) => (
                                        <tr key={index}>
                                            <td className="px-4 py-2 text-gray-900 dark:text-white">{item.description}</td>
                                            <td className="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{item.quantity}</td>
                                            <td className="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{formatCurrency(item.rate)}</td>
                                            <td className="px-4 py-2 text-right font-medium text-gray-900 dark:text-white">{formatCurrency(item.amount)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            <div className="flex justify-end">
                                <div className="w-64 space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Subtotal:</span>
                                        <span className="font-medium text-gray-900 dark:text-white">{formatCurrency(selectedInvoice.subtotal)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Tax ({selectedInvoice.tax_rate}%):</span>
                                        <span className="font-medium text-gray-900 dark:text-white">{formatCurrency(selectedInvoice.tax_amount)}</span>
                                    </div>
                                    <div className="flex justify-between text-lg font-semibold border-t border-gray-200 dark:border-gray-700 pt-2">
                                        <span className="text-gray-900 dark:text-white">Total:</span>
                                        <span className="text-primary-600">{formatCurrency(selectedInvoice.total, selectedInvoice.currency)}</span>
                                    </div>
                                </div>
                            </div>

                            {selectedInvoice.notes && (
                                <div className="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                    <h3 className="text-xs font-medium text-gray-500 uppercase mb-2">Notes</h3>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">{selectedInvoice.notes}</p>
                                </div>
                            )}
                        </div>
                        <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                            <button onClick={() => setShowViewModal(false)} className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Close</button>
                            <button onClick={() => handleSend(selectedInvoice)} className="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 flex items-center gap-2">
                                <Send className="w-4 h-4" /> Send Invoice
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </DashboardLayout>
    );
}
