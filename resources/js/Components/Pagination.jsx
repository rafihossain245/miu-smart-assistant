import React from 'react';
import { router } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/react/24/outline';

export default function Pagination({ data, className = '' }) {
    if (!data.links || data.links.length <= 3) {
        return null;
    }

    const handleClick = (url) => {
        if (!url) return;
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <div className={`flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 ${className}`}>
            <div className="flex flex-1 justify-between sm:hidden">
                {/* Mobile pagination */}
                <button
                    onClick={() => handleClick(data.prev_page_url)}
                    disabled={!data.prev_page_url}
                    className="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Previous
                </button>
                <button
                    onClick={() => handleClick(data.next_page_url)}
                    disabled={!data.next_page_url}
                    className="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Next
                </button>
            </div>

            <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-gray-700">
                        Showing{' '}
                        <span className="font-medium">{data.from}</span>
                        {' '}to{' '}
                        <span className="font-medium">{data.to}</span>
                        {' '}of{' '}
                        <span className="font-medium">{data.total}</span>
                        {' '}results
                    </p>
                </div>

                <div>
                    <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                        {data.links.map((link, index) => {
                            const isFirst = index === 0;
                            const isLast = index === data.links.length - 1;
                            const isActive = link.active;
                            const isDisabled = !link.url;

                            // For first and last links (Previous/Next), show icons
                            if (isFirst || isLast) {
                                return (
                                    <button
                                        key={index}
                                        onClick={() => handleClick(link.url)}
                                        disabled={isDisabled}
                                        className={`relative inline-flex items-center px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed ${
                                            isFirst ? 'rounded-l-md' : 'rounded-r-md'
                                        }`}
                                    >
                                        <span className="sr-only">{link.label}</span>
                                        {isFirst ? (
                                            <ChevronLeftIcon className="h-5 w-5" />
                                        ) : (
                                            <ChevronRightIcon className="h-5 w-5" />
                                        )}
                                    </button>
                                );
                            }

                            // For number links
                            return (
                                <button
                                    key={index}
                                    onClick={() => handleClick(link.url)}
                                    disabled={isDisabled}
                                    className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed ${
                                        isActive
                                            ? 'z-10 bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                                            : 'text-gray-900'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            );
                        })}
                    </nav>
                </div>
            </div>
        </div>
    );
}