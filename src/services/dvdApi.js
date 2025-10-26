// API service for DVD data
const API_BASE_URL = 'https://www.realmsofsilver.com/api';

class DvdApiService {
  constructor() {
    this.baseUrl = API_BASE_URL;
  }

  async fetchDvds(page = 1, limit = 10, q = null) {
    try {
      const params = new URLSearchParams({
        page: page.toString(),
        limit: limit.toString()
      });
      if (q && typeof q === 'string' && q.trim() !== '') {
        params.set('q', q.trim());
      }
      
  const response = await fetch(`${this.baseUrl}/dvds.php?${params}`);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      // Handle error responses from PHP
      if (data.error) {
        throw new Error(data.error);
      }
      
      return data;
    } catch (error) {
      console.error('Error fetching DVDs:', error);
      throw error;
    }
  }

  async createDvd(dvdData) {
    try {
      const response = await fetch(`${this.baseUrl}/dvds.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(dvdData)
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.error) {
        throw new Error(data.error);
      }
      
      return data;
    } catch (error) {
      console.error('Error creating DVD:', error);
      throw error;
    }
  }

  async updateDvd(dvdData) {
    try {
      const response = await fetch(`${this.baseUrl}/dvds.php`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(dvdData)
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.error) {
        throw new Error(data.error);
      }
      
      return data;
    } catch (error) {
      console.error('Error updating DVD:', error);
      throw error;
    }
  }

  async deleteDvd(id) {
    try {
      const response = await fetch(`${this.baseUrl}/dvds.php?id=${id}`, {
        method: 'DELETE'
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.error) {
        throw new Error(data.error);
      }
      
      return data;
    } catch (error) {
      console.error('Error deleting DVD:', error);
      throw error;
    }
  }

  // Helper method to create image URL from base64 data
  getImageUrl(imageData) {
    if (imageData && imageData.type && imageData.data) {
      // Check if data is already base64 encoded or if it's raw binary
      const base64Data = imageData.data.startsWith('data:') ? 
        imageData.data : 
        `data:image/${imageData.type};base64,${imageData.data}`;
      return base64Data;
    }
    return null;
  }
}

export default new DvdApiService();