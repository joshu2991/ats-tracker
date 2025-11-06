import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Sparkles, Github, Home, AlertCircle } from 'lucide-react';

interface Props {
    status?: number;
    message?: string;
}

export default function Error({ status = 500, message }: Props) {
    const { github_url } = usePage<{ github_url?: string }>().props;

    return (
        <>
            <Head title={`${status} - Error`} />
            
            <div className="min-h-screen bg-white">
                {/* Navigation Bar */}
                <nav className="sticky top-0 z-40 h-16 bg-white/90 backdrop-blur-md border-b border-slate-200">
                    <div className="max-w-[1280px] mx-auto px-4 h-full flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Sparkles className="w-5 h-5 text-indigo-600" />
                            <span className="text-xl font-semibold text-slate-900">ATS Tracker</span>
                        </div>
                        <a
                            href={github_url || 'https://github.com'}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors"
                        >
                            <Github className="w-4 h-4" />
                            View on GitHub
                        </a>
                    </div>
                </nav>

                {/* Main Content */}
                <main className="max-w-[1280px] mx-auto px-4 py-24">
                    <div className="flex flex-col items-center justify-center text-center">
                        {/* Error Icon */}
                        <motion.div
                            initial={{ opacity: 0, scale: 0.8 }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={{ duration: 0.6 }}
                            className="mb-8"
                        >
                            <div className="relative">
                                <div className="w-32 h-32 bg-gradient-to-br from-rose-100 to-red-100 rounded-full flex items-center justify-center shadow-lg">
                                    <AlertCircle className="w-16 h-16 text-rose-600" />
                                </div>
                                <div className="absolute -top-2 -right-2 w-12 h-12 bg-rose-600 rounded-full flex items-center justify-center shadow-md">
                                    <span className="text-2xl font-bold text-white">{status}</span>
                                </div>
                            </div>
                        </motion.div>

                        {/* Error Message */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6, delay: 0.2 }}
                            className="max-w-[600px] mb-8"
                        >
                            <h1 className="text-[56px] font-bold leading-[1.1] tracking-[-0.02em] text-slate-900 mb-4">
                                Something{' '}
                                <span className="bg-gradient-to-r from-rose-600 to-red-600 bg-clip-text text-transparent">
                                    Went Wrong
                                </span>
                            </h1>
                            <p className="text-xl leading-relaxed text-slate-600 mb-6">
                                {message || 'We encountered an unexpected error. Please try again or return to the home page.'}
                            </p>
                        </motion.div>

                        {/* Action Buttons */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6, delay: 0.4 }}
                            className="flex flex-col sm:flex-row gap-4 mb-12"
                        >
                            <Link
                                href="/resume-checker"
                                className="inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 hover:-translate-y-0.5 transition-all duration-200 shadow-[0_4px_6px_rgba(79,70,229,0.3)]"
                            >
                                <Home className="w-4 h-4" />
                                Go to Home
                            </Link>
                        </motion.div>
                    </div>
                </main>
            </div>
        </>
    );
}

