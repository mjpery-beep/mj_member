#!/bin/bash

# Quick Start Script for Preact Development
# Usage: ./quickstart.sh

set -e

echo "🚀 MJ Member - Animateur Module Preact Setup"
echo "=============================================="
echo ""

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed"
    echo "Please install Node.js from https://nodejs.org/"
    exit 1
fi

echo "✅ Node.js $(node --version) detected"

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed"
    exit 1
fi

echo "✅ npm $(npm --version) detected"
echo ""

# Install dependencies
echo "📦 Installing dependencies..."
npm install

if [ $? -eq 0 ]; then
    echo "✅ Dependencies installed successfully"
else
    echo "❌ Failed to install dependencies"
    exit 1
fi

echo ""

# Build the project
echo "🔨 Building production bundle..."
npm run build

if [ $? -eq 0 ]; then
    echo "✅ Build completed successfully"
    echo ""
    echo "📊 Bundle info:"
    ls -lh js/dist/animateur-account.js
else
    echo "❌ Build failed"
    exit 1
fi

echo ""
echo "✨ Setup complete!"
echo ""
echo "Next steps:"
echo "1. npm run dev    - Start development server"
echo "2. npm run build  - Build for production"
echo "3. Read DEVELOPMENT_GUIDE.md for more information"
echo ""
