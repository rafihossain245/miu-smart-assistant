import React, { useState } from 'react';
import { useForm, Link, router } from '@inertiajs/react';
import ChatbotLayout from '../../Layouts/ChatbotLayout';
import { PlusIcon, ShoppingBagIcon, CubeIcon, StarIcon, EyeIcon, PencilIcon, TrashIcon, SparklesIcon } from '@heroicons/react/24/outline';
import { StarIcon as StarIconSolid } from '@heroicons/react/24/solid';

export default function ChatbotProducts({ chatbot, products }) {
    const [showAddForm, setShowAddForm] = useState(false);
    const [editingProduct, setEditingProduct] = useState(null);
    const [extractionInput, setExtractionInput] = useState('');
    const [extracting, setExtracting] = useState(false);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        type: 'product',
        description: '',
        short_description: '',
        pricing: [],
        features: [],
        specifications: [],
        primary_url: '',
        demo_url: '',
        documentation_url: '',
        additional_urls: [],
        image_url: '',
        gallery_urls: [],
        video_url: '',
        target_audience: [],
        use_cases: [],
        industries: [],
        ai_summary: '',
        common_questions: [],
        key_benefits: [],
        competitors: [],
        keywords: [],
        meta_description: '',
        tags: [],
        is_active: true,
        is_featured: false,
        sort_order: 0,
    });

    const submit = (e) => {
        e.preventDefault();

        if (editingProduct) {
            put(`/chatbots/${chatbot.id}/products/${editingProduct.id}`, {
                onSuccess: () => {
                    reset();
                    setEditingProduct(null);
                    setShowAddForm(false);
                }
            });
        } else {
            post(`/chatbots/${chatbot.id}/products`, {
                onSuccess: () => {
                    reset();
                    setShowAddForm(false);
                }
            });
        }
    };

    const editProduct = (product) => {
        setData({
            name: product.name || '',
            type: product.type || 'product',
            description: product.description || '',
            short_description: product.short_description || '',
            pricing: product.pricing || [],
            features: product.features || [],
            specifications: product.specifications || [],
            primary_url: product.primary_url || '',
            demo_url: product.demo_url || '',
            documentation_url: product.documentation_url || '',
            additional_urls: product.additional_urls || [],
            image_url: product.image_url || '',
            gallery_urls: product.gallery_urls || [],
            video_url: product.video_url || '',
            target_audience: product.target_audience || [],
            use_cases: product.use_cases || [],
            industries: product.industries || [],
            ai_summary: product.ai_summary || '',
            common_questions: product.common_questions || [],
            key_benefits: product.key_benefits || [],
            competitors: product.competitors || [],
            keywords: product.keywords || [],
            meta_description: product.meta_description || '',
            tags: product.tags || [],
            is_active: product.is_active ?? true,
            is_featured: product.is_featured ?? false,
            sort_order: product.sort_order || 0,
        });
        setEditingProduct(product);
        setShowAddForm(true);
    };

    const cancelEdit = () => {
        reset();
        setEditingProduct(null);
        setShowAddForm(false);
        setExtractionInput('');
    };

    const extractProductInfo = async () => {
        if (!extractionInput.trim()) {
            alert('Please enter a URL or product description');
            return;
        }

        setExtracting(true);

        try {
            const response = await fetch(`/chatbots/${chatbot.id}/extract-product-info`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({
                    input: extractionInput
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error('Server returned non-JSON response. Please try again.');
            }

            const result = await response.json();

            if (result.success && result.data) {
                // Fill the form with extracted data
                const extractedData = result.data;
                setData({
                    name: extractedData.name || '',
                    type: extractedData.type || 'product',
                    description: extractedData.description || '',
                    short_description: extractedData.short_description || '',
                    features: extractedData.features || [],
                    key_benefits: extractedData.key_benefits || [],
                    use_cases: extractedData.use_cases || [],
                    target_audience: extractedData.target_audience || [],
                    keywords: extractedData.keywords || [],
                    primary_url: extractedData.primary_url || '',
                    meta_description: extractedData.meta_description || '',
                    is_active: extractedData.is_active !== undefined ? extractedData.is_active : true,
                    is_featured: extractedData.is_featured !== undefined ? extractedData.is_featured : false,
                    sort_order: extractedData.sort_order || 0,
                    // Keep existing values for other fields
                    pricing: data.pricing,
                    specifications: data.specifications,
                    demo_url: data.demo_url,
                    documentation_url: data.documentation_url,
                    additional_urls: data.additional_urls,
                    image_url: data.image_url,
                    gallery_urls: data.gallery_urls,
                    video_url: data.video_url,
                    industries: data.industries,
                    ai_summary: data.ai_summary,
                    common_questions: data.common_questions,
                    competitors: data.competitors,
                    tags: data.tags,
                });

                setExtractionInput('');
                setShowAddForm(true);
                alert('✅ Product information extracted successfully! Please review and modify as needed.');
            } else {
                alert('❌ ' + (result.error || 'Failed to extract product information'));
            }
        } catch (error) {
            alert('❌ Error: ' + error.message);
        }

        setExtracting(false);
    };

    const deleteProduct = (productId) => {
        if (confirm('Are you sure you want to delete this product/service?')) {
            router.delete(`/chatbots/${chatbot.id}/products/${productId}`);
        }
    };

    // Helper function to add/remove items from arrays
    const updateArrayField = (field, value) => {
        const array = data[field] || [];
        setData(field, [...array, value]);
    };

    const removeArrayItem = (field, index) => {
        const array = data[field] || [];
        setData(field, array.filter((_, i) => i !== index));
    };

    return (
        <ChatbotLayout chatbot={chatbot} title={`Products & Services - ${chatbot.name}`}>
            <div className="px-4 py-6 sm:px-0">
                <div className="sm:flex sm:items-center">
                    <div className="sm:flex-auto">
                        <h1 className="text-base font-semibold leading-6 text-gray-900">
                            Products & Services for {chatbot.name}
                        </h1>
                        <p className="mt-2 text-sm text-gray-700">
                            Manage your products and services to help your chatbot provide better recommendations and information to users.
                        </p>
                    </div>
                    <div className="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                        <button
                            onClick={() => setShowAddForm(!showAddForm)}
                            className="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                        >
                            <PlusIcon className="h-4 w-4 inline mr-1" />
                            Add {products.data.length === 0 ? 'First ' : ''}Product/Service
                        </button>
                    </div>
                </div>

                {showAddForm && (
                    <div className="mt-8 bg-white shadow sm:rounded-lg">
                        <div className="px-4 py-5 sm:p-6">
                            <h3 className="text-base font-semibold leading-6 text-gray-900">
                                {editingProduct ? 'Edit' : 'Add New'} Product/Service
                            </h3>
                            <form onSubmit={submit} className="mt-6 space-y-6">
                                {/* Smart Extraction Section */}
                                {!editingProduct && (
                                    <div className="border-2 border-dashed border-indigo-300 rounded-lg p-6 bg-indigo-50">
                                        <div className="text-center">
                                            <SparklesIcon className="mx-auto h-12 w-12 text-indigo-400" />
                                            <h3 className="mt-2 text-sm font-semibold text-indigo-900">Smart Product Extraction</h3>
                                            <p className="mt-1 text-sm text-indigo-700">
                                                Enter a product URL or describe your product/service to auto-fill the form
                                            </p>
                                            <div className="mt-4">
                                                <div className="flex rounded-md shadow-sm">
                                                    <input
                                                        type="text"
                                                        value={extractionInput}
                                                        onChange={(e) => setExtractionInput(e.target.value)}
                                                        className="flex-1 rounded-l-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                        placeholder="https://example.com/product or describe your product..."
                                                        disabled={extracting}
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={extractProductInfo}
                                                        disabled={extracting || !extractionInput.trim()}
                                                        className="inline-flex items-center rounded-r-md border border-l-0 border-indigo-300 bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                                    >
                                                        {extracting ? (
                                                            <>
                                                                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                                                Extracting...
                                                            </>
                                                        ) : (
                                                            <>
                                                                <SparklesIcon className="h-4 w-4 mr-2" />
                                                                Extract
                                                            </>
                                                        )}
                                                    </button>
                                                </div>
                                                <p className="mt-2 text-xs text-indigo-600">
                                                    Supports URLs from product pages, or detailed text descriptions of your product/service
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Basic Information */}
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <div>
                                        <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                                            Name *
                                        </label>
                                        <input
                                            type="text"
                                            id="name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                            placeholder="Product or service name"
                                        />
                                        {errors.name && <p className="mt-2 text-sm text-red-600">{errors.name}</p>}
                                    </div>

                                    <div>
                                        <label htmlFor="type" className="block text-sm font-medium text-gray-700">
                                            Type *
                                        </label>
                                        <select
                                            id="type"
                                            value={data.type}
                                            onChange={(e) => setData('type', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                        >
                                            <option value="product">Product</option>
                                            <option value="service">Service</option>
                                        </select>
                                        {errors.type && <p className="mt-2 text-sm text-red-600">{errors.type}</p>}
                                    </div>
                                </div>

                                <div>
                                    <label htmlFor="short_description" className="block text-sm font-medium text-gray-700">
                                        Short Description
                                    </label>
                                    <input
                                        type="text"
                                        id="short_description"
                                        value={data.short_description}
                                        onChange={(e) => setData('short_description', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                        placeholder="Brief one-line description"
                                        maxLength={500}
                                    />
                                    {errors.short_description && <p className="mt-2 text-sm text-red-600">{errors.short_description}</p>}
                                </div>

                                <div>
                                    <label htmlFor="description" className="block text-sm font-medium text-gray-700">
                                        Detailed Description *
                                    </label>
                                    <textarea
                                        id="description"
                                        rows={4}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                        placeholder="Detailed description of your product or service"
                                    />
                                    {errors.description && <p className="mt-2 text-sm text-red-600">{errors.description}</p>}
                                </div>

                                {/* URLs */}
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                                    <div>
                                        <label htmlFor="primary_url" className="block text-sm font-medium text-gray-700">
                                            Primary URL
                                        </label>
                                        <input
                                            type="url"
                                            id="primary_url"
                                            value={data.primary_url}
                                            onChange={(e) => setData('primary_url', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                            placeholder="https://example.com/product"
                                        />
                                        {errors.primary_url && <p className="mt-2 text-sm text-red-600">{errors.primary_url}</p>}
                                    </div>

                                    <div>
                                        <label htmlFor="demo_url" className="block text-sm font-medium text-gray-700">
                                            Demo/Trial URL
                                        </label>
                                        <input
                                            type="url"
                                            id="demo_url"
                                            value={data.demo_url}
                                            onChange={(e) => setData('demo_url', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                            placeholder="https://demo.example.com"
                                        />
                                        {errors.demo_url && <p className="mt-2 text-sm text-red-600">{errors.demo_url}</p>}
                                    </div>

                                    <div>
                                        <label htmlFor="image_url" className="block text-sm font-medium text-gray-700">
                                            Image URL
                                        </label>
                                        <input
                                            type="url"
                                            id="image_url"
                                            value={data.image_url}
                                            onChange={(e) => setData('image_url', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2"
                                            placeholder="https://example.com/image.jpg"
                                        />
                                        {errors.image_url && <p className="mt-2 text-sm text-red-600">{errors.image_url}</p>}
                                    </div>
                                </div>

                                {/* Settings */}
                                <div className="flex items-center space-x-6">
                                    <div className="flex items-center">
                                        <input
                                            id="is_active"
                                            type="checkbox"
                                            checked={data.is_active}
                                            onChange={(e) => setData('is_active', e.target.checked)}
                                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <label htmlFor="is_active" className="ml-2 block text-sm text-gray-900">
                                            Active
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            id="is_featured"
                                            type="checkbox"
                                            checked={data.is_featured}
                                            onChange={(e) => setData('is_featured', e.target.checked)}
                                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <label htmlFor="is_featured" className="ml-2 block text-sm text-gray-900">
                                            Featured
                                        </label>
                                    </div>
                                </div>

                                <div className="flex justify-end space-x-3">
                                    <button
                                        type="button"
                                        onClick={cancelEdit}
                                        className="rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
                                    >
                                        {processing ? 'Saving...' : editingProduct ? 'Update' : 'Create'} Product/Service
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Products List */}
                <div className="mt-8">
                    {products.data.length === 0 ? (
                        <div className="text-center py-12">
                            <ShoppingBagIcon className="mx-auto h-12 w-12 text-gray-400" />
                            <h3 className="mt-2 text-sm font-semibold text-gray-900">No products or services</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Get started by adding your first product or service to help your chatbot provide better recommendations.
                            </p>
                        </div>
                    ) : (
                        <div className="bg-white shadow overflow-hidden sm:rounded-md">
                            <ul className="divide-y divide-gray-200">
                                {products.data.map((product) => (
                                    <li key={product.id}>
                                        <div className="px-4 py-4 flex items-center justify-between">
                                            <div className="flex items-center">
                                                <div className="flex-shrink-0">
                                                    {product.type === 'product' ? (
                                                        <CubeIcon className="h-6 w-6 text-gray-400" />
                                                    ) : (
                                                        <ShoppingBagIcon className="h-6 w-6 text-gray-400" />
                                                    )}
                                                </div>
                                                <div className="ml-4">
                                                    <div className="flex items-center">
                                                        <div className="text-sm font-medium text-gray-900">
                                                            {product.name}
                                                        </div>
                                                        {product.is_featured && (
                                                            <StarIconSolid className="ml-2 h-4 w-4 text-yellow-400" />
                                                        )}
                                                        {!product.is_active && (
                                                            <span className="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                Inactive
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {product.type.charAt(0).toUpperCase() + product.type.slice(1)}
                                                        {product.short_description && ` • ${product.short_description}`}
                                                        {product.mention_count > 0 && ` • ${product.mention_count} mentions`}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                {product.primary_url && (
                                                    <a
                                                        href={product.primary_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-gray-400 hover:text-gray-500"
                                                        title="View product page"
                                                    >
                                                        <EyeIcon className="h-4 w-4" />
                                                    </a>
                                                )}
                                                <button
                                                    onClick={() => editProduct(product)}
                                                    className="text-indigo-600 hover:text-indigo-900"
                                                    title="Edit product"
                                                >
                                                    <PencilIcon className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={() => deleteProduct(product.id)}
                                                    className="text-red-600 hover:text-red-900"
                                                    title="Delete product"
                                                >
                                                    <TrashIcon className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </ChatbotLayout>
    );
}