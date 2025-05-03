import React, { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { 
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer
} from 'recharts';
import { useAppContext } from '../context/AppContext';

const ArticleImpactComponent = () => {
  // Menggunakan state global
  const { selectedField, setSelectedField } = useAppContext();
  
  // Data dummy untuk artikel
  const topArticlesData = [
    { 
      id: 1, 
      title: 'Implementasi Deep Learning untuk Pengenalan Pola Batik Indonesia', 
      authors: 'Wijaya, S., Santoso, R.', 
      journal: 'Jurnal Informatika Indonesia',
      year: 2023,
      citations: 28,
      social_mentions: 45,
      practical_uses: 12,
      sintaAccreditation: 'SINTA 2',
      impactScore: 87.4
    },
    // ... data lainnya
  ];
  
  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-blue-700 text-white p-4 shadow-md">
        <div className="container mx-auto flex justify-between items-center">
          <Link to="/" className="flex items-center">
            <img src="/logo.png" alt="Wizdom Indonesia Logo" className="w-10 h-10 mr-3" />
            <h1 className="text-xl font-bold">Wizdom Indonesia</h1>
          </Link>
        </div>
      </header>
      
      {/* Navigation - menggunakan Link dari react-router untuk navigasi */}
      <nav className="bg-white shadow-sm">
        <div className="container mx-auto">
          <ul className="flex flex-wrap space-x-1 md:space-x-8 p-4">
            <li>
              <Link 
                to="/"
                className="font-medium text-gray-600 hover:text-blue-700"
              >
                Dashboard
              </Link>
            </li>
            <li>
              <Link 
                to="/article-impact"
                className="font-medium text-blue-700 border-b-2 border-blue-700"
              >
                Dampak Artikel
              </Link>
            </li>
            <li>
              <Link 
                to="/researchers"
                className="font-medium text-gray-600 hover:text-blue-700"
              >
                Peneliti Terkemuka
              </Link>
            </li>
            <li>
              <Link 
                to="/researcher-map"
                className="font-medium text-gray-600 hover:text-blue-700"
              >
                Peta Distribusi
              </Link>
            </li>
            <li>
              <Link 
                to="/trends"
                className="font-medium text-gray-600 hover:text-blue-700"
              >
                Tren & Analisis
              </Link>
            </li>
          </ul>
        </div>
      </nav>
      
      {/* Main Content - placeholder untuk konten Dampak Artikel */}
      <main className="container mx-auto p-4 md:p-6">
        <h2 className="text-lg font-semibold mb-4">Dampak Artikel Penelitian</h2>
        
        {/* Konten placeholder */}
        <div className="bg-white p-4 rounded-lg shadow">
          <p className="text-center text-gray-600 my-8">
            Konten untuk halaman Dampak Artikel akan ditampilkan di sini
          </p>
        </div>
      </main>
      
      {/* Footer */}
      <footer className="bg-gray-100 p-4 mt-6">
        <div className="container mx-auto text-center text-sm text-gray-600">
          <p>© 2025 Wizdom Indonesia - Platform Analisis Dampak Penelitian Indonesia</p>
          <p className="mt-1">Versi 1.0 - Dikembangkan oleh Tim Wizdom Indonesia</p>
        </div>
      </footer>
    </div>
  );
};

export default ArticleImpactComponent;
