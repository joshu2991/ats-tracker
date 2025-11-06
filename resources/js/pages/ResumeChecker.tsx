import { router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useRef, useState } from 'react';
import { motion } from 'framer-motion';
import {
    Sparkles,
    CloudUpload,
    FileText,
    Upload,
    FileSearch,
    Hash,
    Target,
    TrendingUp,
    AlertCircle,
    Lightbulb,
    Shield,
    Lock,
    Zap,
    Github,
    CheckCircle2,
} from 'lucide-react';
import AnalysisLoadingModal from '../components/AnalysisLoadingModal';
import ResumeResultsDashboard from '../components/ResumeResultsDashboard';
import SEOHead from '../components/SEOHead';
import ToastContainer, { useToast } from '../components/ToastContainer';

interface Analysis {
    filename?: string;
    overall_score?: number;
    confidence?: 'high' | 'medium' | 'low';
    parseability_score?: number;
    format_score?: number;
    keyword_score?: number;
    contact_score?: number;
    content_score?: number;
    critical_issues?: string[];
    warnings?: string[];
    suggestions?: string[];
    ai_unavailable?: boolean;
    ai_error_message?: string | null;
    estimated_cost?: number;
}

interface Props {
    analysis?: Analysis;
}

export default function ResumeChecker({ analysis }: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [dragActive, setDragActive] = useState(false);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const { showToast, removeToast, toasts } = useToast();
    const rateLimitToastShown = useRef<boolean>(false); // Track if we've shown rate limit toast
    const { github_url, seo } = usePage<{ github_url?: string; seo?: any }>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        resume: null as File | null,
    });

    const handleDrag = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true);
        } else if (e.type === 'dragleave') {
            setDragActive(false);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleFileSelect(e.dataTransfer.files[0]);
        }
    };

    const handleFileSelect = (file: File) => {
        // Validate file type
        const validTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        const validExtensions = ['.pdf', '.docx'];

        const fileExtension = '.' + file.name.split('.').pop()?.toLowerCase();
        const isValidType = validTypes.includes(file.type) || validExtensions.includes(fileExtension);

        if (!isValidType) {
            showToast('Please upload a PDF or DOCX file.', 'error');
            return;
        }

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            showToast('File size must be less than 5MB.', 'error');
            return;
        }

        setSelectedFile(file);
        setData('resume', file);
    };

    const handleFileInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            handleFileSelect(e.target.files[0]);
        }
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (data.resume) {
            // Reset rate limit toast flag when submitting new request
            rateLimitToastShown.current = false;
            post('/resume/analyze', {
                forceFormData: true,
                onError: (errors) => {
                    // Handle rate limit error
                    if (errors.rate_limit && !rateLimitToastShown.current) {
                        rateLimitToastShown.current = true;
                        showToast(errors.rate_limit, 'warning', 8000);
                    }
                },
            });
        }
    };

    // Check for rate limit errors from flash messages (only on initial page load)
    const { errors: pageErrors } = usePage().props as any;
    useEffect(() => {
        // Only show toast if error exists and we haven't already shown it
        // This prevents duplicate toasts when the form's onError also fires
        if (pageErrors?.rate_limit && !rateLimitToastShown.current && !processing) {
            rateLimitToastShown.current = true;
            showToast(pageErrors.rate_limit, 'warning', 8000);
        }
    }, [pageErrors?.rate_limit, processing, showToast]);

    const handleReset = () => {
        setSelectedFile(null);
        reset();
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
        // Clear session and navigate to upload page
        router.visit('/resume-checker?clear=true', {
            preserveState: false,
            preserveScroll: false,
        });
    };

    const handleExampleResume = () => {
        // Trigger file input to select example resume
        if (fileInputRef.current) {
            fileInputRef.current.click();
        }
    };

    // If we have analysis results, show the results page
    if (analysis && analysis.overall_score !== undefined) {
        return (
            <>
                {seo && <SEOHead {...seo} />}
                <AnalysisLoadingModal isOpen={processing} />
                <ToastContainer toasts={toasts} onRemove={removeToast} />
                <ResumeResultsDashboard analysis={analysis} onReset={handleReset} />
            </>
        );
    }

    // Landing page (before upload)
    return (
        <>
            {seo && <SEOHead {...seo} />}
            <AnalysisLoadingModal isOpen={processing} />
            <ToastContainer toasts={toasts} onRemove={removeToast} />

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

                {/* Hero Section */}
                <main className="max-w-[1280px] mx-auto px-4 py-24">
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-16 items-center mb-24">
                        {/* Left Column */}
                        <div className="lg:col-span-7">
                            {/* Badge */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.1 }}
                                className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-100 rounded-full mb-6"
                            >
                                <Sparkles className="w-4 h-4 text-indigo-700" />
                                <span className="text-sm font-medium text-indigo-700">
                                    Free AI-Powered Analysis • No Signup Required
                                </span>
                            </motion.div>

                            {/* Main Headline */}
                            <motion.h1
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.2 }}
                                className="text-[56px] font-bold leading-[1.1] tracking-[-0.02em] text-slate-900 mb-4 max-w-[600px]"
                            >
                                Get Your Resume{' '}
                                <span className="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                                    ATS Score
                                </span>{' '}
                                in 30 Seconds
                            </motion.h1>

                            {/* Subheadline */}
                            <motion.p
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.3 }}
                                className="text-xl leading-relaxed text-slate-600 mb-8 max-w-[540px]"
                            >
                                Upload your resume and get instant AI-powered feedback on ATS compatibility,
                                formatting, keywords, and actionable suggestions to land more interviews.
                            </motion.p>

                            {/* Stats Row */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.4 }}
                                className="flex gap-6 sm:gap-8 md:gap-12 mb-12"
                            >
                                <div className="flex flex-col gap-1">
                                    <span className="text-2xl sm:text-3xl md:text-4xl font-bold text-indigo-600">2,847+</span>
                                    <span className="text-xs sm:text-sm text-slate-500">Resumes Analyzed</span>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <span className="text-2xl sm:text-3xl md:text-4xl font-bold text-indigo-600">~25s</span>
                                    <span className="text-xs sm:text-sm text-slate-500">Average Analysis Time</span>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <span className="text-2xl sm:text-3xl md:text-4xl font-bold text-indigo-600">100%</span>
                                    <span className="text-xs sm:text-sm text-slate-500">Free Forever</span>
                                </div>
                            </motion.div>

                            {/* Upload Area */}
                            <motion.form
                                onSubmit={handleSubmit}
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.5 }}
                                className="max-w-[560px]"
                            >
                                <div
                                    className={`relative p-10 bg-white border-2 border-dashed rounded-2xl shadow-[0_4px_6px_-1px_rgba(0,0,0,0.1),0_2px_4px_-1px_rgba(0,0,0,0.06)] transition-all duration-300 cursor-pointer ${
                                    dragActive
                                            ? 'border-indigo-600 bg-indigo-100 scale-[1.02]'
                                            : 'border-slate-300 hover:border-indigo-400 hover:shadow-[0_20px_25px_-5px_rgba(0,0,0,0.1),0_10px_10px_-5px_rgba(0,0,0,0.04)] hover:scale-[1.01]'
                                    } ${errors.resume ? 'border-rose-500' : ''}`}
                                onDragEnter={handleDrag}
                                onDragLeave={handleDrag}
                                onDragOver={handleDrag}
                                onDrop={handleDrop}
                            >
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".pdf,.docx"
                                    onChange={handleFileInputChange}
                                        className="absolute inset-0 w-full h-full cursor-pointer opacity-0"
                                    disabled={processing}
                                />

                                    <div className="flex flex-col items-center text-center">
                                        <CloudUpload className="w-12 h-12 text-indigo-600 mb-4" />
                                        <p className="text-lg font-semibold text-slate-900 mb-2">
                                            {selectedFile ? (
                                                <span className="text-indigo-600">{selectedFile.name}</span>
                                            ) : (
                                                'Drop your resume here or click to browse'
                                            )}
                                        </p>
                                        <p className="text-sm text-slate-500 mb-6">
                                            Supports PDF and DOCX • Max 5MB
                                        </p>
                                </div>
                            </div>

                            {errors.resume && (
                                    <div className="mt-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-700">
                                        {errors.resume}
                                </div>
                            )}

                            {selectedFile && (
                                <div className="mt-6 flex gap-4">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                            className="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 shadow-[0_4px_6px_rgba(79,70,229,0.3)]"
                                        >
                                            {processing ? 'Analyzing...' : 'Analyze Resume'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleReset}
                                        disabled={processing}
                                            className="px-6 py-3 border border-slate-300 bg-white text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors disabled:opacity-50"
                                    >
                                        Reset
                                    </button>
                                </div>
                            )}
                            </motion.form>
                        </div>

                        {/* Right Column - Decorative Card Mockup */}
                        <div className="lg:col-span-5 hidden lg:block">
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.6 }}
                                className="relative w-full max-w-[480px] aspect-[4/3] bg-white rounded-[24px] p-8 shadow-[0_25px_50px_-12px_rgba(79,70,229,0.25)] animate-float"
                            >
                                {/* Mini Score Badge */}
                                <div className="absolute -top-3 -right-3 w-20 h-20 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-full flex flex-col items-center justify-center shadow-[0_8px_16px_rgba(16,185,129,0.3)] animate-pulse-scale">
                                    <span className="text-3xl font-bold text-white">78</span>
                                    <span className="text-xs text-white/80">/100</span>
                                </div>

                                <h3 className="text-xl font-semibold text-slate-900 mb-6">Sample Analysis Results</h3>

                                {/* Progress Bars */}
                                <div className="space-y-4 mb-6">
                                    {[
                                        { label: 'Format', score: 85 },
                                        { label: 'Keywords', score: 72 },
                                        { label: 'Content', score: 68 },
                                    ].map((item, index) => (
                                        <div key={index}>
                                            <div className="flex justify-between items-center mb-2">
                                                <span className="text-sm font-medium text-slate-700">{item.label}</span>
                                                <span className="text-sm font-semibold text-indigo-600">
                                                    {item.score}%
                                                </span>
                                            </div>
                                            <div className="h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div
                                                    className={`h-full rounded-full bg-gradient-to-r ${
                                                        item.score >= 70
                                                            ? 'from-emerald-500 to-emerald-600'
                                                            : 'from-amber-500 to-amber-600'
                                                    }`}
                                                    style={{ width: `${item.score}%` }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {/* Mini Suggestions */}
                                <div className="mt-6 pt-6 border-t border-slate-200 space-y-3">
                                    {[
                                        'Add 3-5 more technical keywords',
                                        'Include LinkedIn profile URL',
                                    ].map((suggestion, index) => (
                                        <div key={index} className="flex items-start gap-3">
                                            <CheckCircle2 className="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" />
                                            <span className="text-sm leading-relaxed text-slate-600">{suggestion}</span>
                                        </div>
                                    ))}
                                </div>
                            </motion.div>

                            {/* Trust Indicators */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.7 }}
                                className="mt-10 space-y-3"
                            >
                                {[
                                    { icon: Shield, text: 'Your data is never stored' },
                                    { icon: Lock, text: 'Analyzed locally with AI' },
                                    { icon: Zap, text: 'Results in under 30 seconds' },
                                ].map((item, index) => {
                                    const Icon = item.icon;
                                    return (
                                        <div key={index} className="flex items-center gap-3">
                                            <Icon className="w-5 h-5 text-indigo-600" />
                                            <span className="text-sm text-slate-600">{item.text}</span>
                                        </div>
                                    );
                                })}
                            </motion.div>
                                </div>
                            </div>

                    {/* Features Section */}
                    <motion.section
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6, delay: 0.8 }}
                        className="max-w-[1120px] mx-auto mt-24"
                    >
                        <div className="text-center mb-12">
                            <h2 className="text-4xl font-bold text-slate-900 mb-4">What Gets Analyzed</h2>
                            <p className="text-lg text-slate-600">
                                Comprehensive ATS compatibility check covering all critical factors
                                </p>
                            </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                            {[
                                {
                                    icon: FileSearch,
                                    title: 'Format & Structure',
                                    description: 'Checks for ATS-friendly formatting, standard sections, and proper document structure',
                                },
                                {
                                    icon: Hash,
                                    title: 'Keywords & Skills',
                                    description: 'Analyzes technical keyword density and matches against industry standards',
                                },
                                {
                                    icon: Target,
                                    title: 'Contact & Details',
                                    description: 'Verifies all critical contact information is present and properly formatted',
                                },
                                {
                                    icon: TrendingUp,
                                    title: 'Content Quality',
                                    description: 'Evaluates use of action verbs, quantifiable achievements, and impact metrics',
                                },
                                {
                                    icon: AlertCircle,
                                    title: 'Red Flags',
                                    description: 'Identifies critical issues that could cause automatic rejection by ATS systems',
                                },
                                {
                                    icon: Lightbulb,
                                    title: 'Suggestions',
                                    description: 'Provides specific, actionable recommendations to improve your resume score',
                                },
                            ].map((feature, index) => {
                                const Icon = feature.icon;
                                return (
                                    <motion.div
                                        key={index}
                                        initial={{ opacity: 0, y: 20 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        transition={{ delay: 0.9 + index * 0.1, duration: 0.6 }}
                                        className="bg-white p-8 rounded-2xl border border-slate-200 shadow-[0_4px_6px_-1px_rgba(0,0,0,0.1),0_2px_4px_-1px_rgba(0,0,0,0.06)] hover:shadow-[0_20px_25px_-5px_rgba(0,0,0,0.1),0_10px_10px_-5px_rgba(0,0,0,0.04)] hover:-translate-y-1 transition-all duration-300"
                                    >
                                        <div className="w-14 h-14 bg-indigo-100 rounded-xl flex items-center justify-center mb-5">
                                            <Icon className="w-7 h-7 text-indigo-600" />
                            </div>
                                        <h3 className="text-lg font-semibold text-slate-900 mb-3">{feature.title}</h3>
                                        <p className="text-sm leading-relaxed text-slate-600">{feature.description}</p>
                                    </motion.div>
                                );
                            })}
                        </div>
                    </motion.section>
                </main>
            </div>
        </>
    );
}
