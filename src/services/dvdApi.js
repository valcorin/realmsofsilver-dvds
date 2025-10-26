// API service for DVD data
const API_BASE_URL = 'https://www.realmsofsilver.com/api';

class DvdApiService {
  constructor() {
    this.baseUrl = API_BASE_URL;
  }

  async fetchDvds() {
    try {
      const response = await fetch(`${this.baseUrl}/dvds.php`);
      
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
      return `data:image/${imageData.type};base64,${imageData.data}`;
    }
    return null;
  }
}

export default new DvdApiService();