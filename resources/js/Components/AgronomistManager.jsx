import React, { useState, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import axios from 'axios';

export default function AgronomistManager({ producer }) {
  const [agronomists, setAgronomists] = useState([]);
  const [availableAgronomists, setAvailableAgronomists] = useState([]);
  const [selectedAgronomist, setSelectedAgronomist] = useState(null);

  const { post, delete: destroy } = useForm();

  useEffect(() => {
    setAgronomists(producer.agronomists || []);
    fetchAvailableAgronomists();
  }, [producer]);

  const fetchAvailableAgronomists = async () => {
    try {
      const response = await axios.get(route('agronomists.index'));
      const allAgronomists = response.data;
      const currentAgronomistIds = producer.agronomists.map(a => a.id);
      const filteredAgronomists = allAgronomists.filter(ag => !currentAgronomistIds.includes(ag.id));
      setAvailableAgronomists(filteredAgronomists);
    } catch (error) {
      console.error("Error fetching available agronomists:", error);
    }
  };

  const handleAttachAgronomist = () => {
    if (selectedAgronomist) {
      post(route('producers.agronomists.store', producer.id), {
        producer_id: producer.id,
        agronomist_id: selectedAgronomist,
        onSuccess: () => {
          setSelectedAgronomist(null);
          // Refresh producer data to get updated agronomists list
          window.location.reload(); 
        },
      });
    }
  };

  const handleDetachAgronomist = (agronomistId) => {
    destroy(route('producers.agronomists.destroy', producer.id), {
      data: { producer_id: producer.id, agronomist_id: agronomistId },
      onSuccess: () => {
        // Refresh producer data to get updated agronomists list
        window.location.reload();
      },
    });
  };

  return (
    <div>
      <div className="flex items-center space-x-2 mb-4">
        <Select onValueChange={setSelectedAgronomist} value={selectedAgronomist}>
          <SelectTrigger className="w-[200px]">
            <SelectValue placeholder="Select Agronomist" />
          </SelectTrigger>
          <SelectContent>
            {availableAgronomists.map((agronomist) => (
              <SelectItem key={agronomist.id} value={agronomist.id}>
                {agronomist.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Button onClick={handleAttachAgronomist} disabled={!selectedAgronomist}>
          Add Agronomist
        </Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Email</TableHead>
            <TableHead>Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {agronomists.map((agronomist) => (
            <TableRow key={agronomist.id}>
              <TableCell>{agronomist.name}</TableCell>
              <TableCell>{agronomist.email}</TableCell>
              <TableCell>
                <Button
                  variant="destructive"
                  size="sm"
                  onClick={() => handleDetachAgronomist(agronomist.id)}
                >
                  Remove
                </Button>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
